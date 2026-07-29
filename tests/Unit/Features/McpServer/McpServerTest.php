<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\McpServer;

use AgentGateMcp\Features\McpServer\McpServer;
use AgentGateMcp\Features\McpServer\ToolRegistry;
use AgentGateMcp\Features\Settings\PluginSettings;
use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Exception\ToolCallException;
use AgentGateMcp\Shared\JsonRpc\JsonRpcErrorCode;
use AgentGateMcp\Shared\JsonRpc\JsonRpcRequest;
use AgentGateMcp\Shared\Tool\ToolGroup;
use AgentGateMcp\Tests\Doubles\AgentFactory;
use AgentGateMcp\Tests\Doubles\StubTool;
use AgentGateMcp\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * Pins the protocol contract before the tool surface grows: what reaches a
 * tool, what an agent is told when it may not, and how failures are shaped.
 */
final class McpServerTest extends TestCase {

	/** @var list<array{string, string, bool, array}> */
	private array $logged = [];

	protected function setUp(): void {
		parent::setUp();

		$this->logged = [];

		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof \WP_Error );
		Functions\when( 'wp_json_encode' )->alias( static fn ( $data ): string => (string) wp_json_encode_stub( $data ) );
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Store' );

		// The schema validators are core's; here they only need to pass values
		// through so the server's own default-application logic is observable.
		Functions\when( 'rest_sanitize_value_from_schema' )->returnArg( 1 );
		Functions\when( 'rest_validate_value_from_schema' )->returnArg( 1 );

		Functions\when( 'do_action' )->alias(
			function ( string $hook, ...$args ): void {
				if ( 'agmcp_tool_called' === $hook ) {
					$this->logged[] = $args;
				}
			}
		);
	}

	private function request( string $method, array $params = [], string|int|null $id = 1 ): JsonRpcRequest {
		return JsonRpcRequest::from_body(
			(string) wp_json_encode_stub(
				[
					'jsonrpc' => '2.0',
					'id'      => $id,
					'method'  => $method,
					'params'  => $params,
				]
			)
		);
	}

	private function server( ToolRegistry $registry ): McpServer {
		return new McpServer( $registry );
	}

	private function registry_with( StubTool ...$tools ): ToolRegistry {
		Functions\when( 'get_option' )->justReturn(
			[
				'products_read'  => true,
				'products_write' => true,
			]
		);

		$registry = new ToolRegistry( new PluginSettings() );
		foreach ( $tools as $tool ) {
			$registry->add( $tool );
		}

		return $registry;
	}

	public function test_notifications_get_no_response(): void {
		$registry = $this->registry_with();

		$this->assertNull(
			$this->server( $registry )->handle(
				$this->request( 'notifications/initialized' ),
				AgentFactory::with_scopes( [] )
			)
		);
	}

	public function test_unknown_method_is_a_protocol_error(): void {
		$registry = $this->registry_with();

		$response = $this->server( $registry )->handle( $this->request( 'does/not/exist' ), AgentFactory::with_scopes( [] ) );

		$this->assertSame( JsonRpcErrorCode::MethodNotFound->value, $response->payload['error']['code'] );
	}

	public function test_initialize_agrees_a_known_protocol_version(): void {
		$registry = $this->registry_with();

		$response = $this->server( $registry )->handle(
			$this->request( 'initialize', [ 'protocolVersion' => '2024-11-05' ] ),
			AgentFactory::with_scopes( [ 'products:read' ] )
		);

		$this->assertSame( '2024-11-05', $response->payload['result']['protocolVersion'] );
	}

	public function test_initialize_falls_back_to_the_servers_version_when_unknown(): void {
		$registry = $this->registry_with();

		$response = $this->server( $registry )->handle(
			$this->request( 'initialize', [ 'protocolVersion' => '1999-01-01' ] ),
			AgentFactory::with_scopes( [] )
		);

		$this->assertSame( McpServer::PROTOCOL_VERSION, $response->payload['result']['protocolVersion'] );
	}

	public function test_tools_list_exposes_name_description_and_schema_only(): void {
		$registry = $this->registry_with( new StubTool( 'list_products', ApiScope::ProductsRead, ToolGroup::Products ) );

		$response = $this->server( $registry )->handle(
			$this->request( 'tools/list' ),
			AgentFactory::with_scopes( [ 'products:read' ] )
		);

		$this->assertCount( 1, $response->payload['result']['tools'] );
		$this->assertSame(
			[ 'name', 'description', 'inputSchema' ],
			array_keys( $response->payload['result']['tools'][0] )
		);
	}

	public function test_calling_a_tool_outside_scope_reads_as_unknown(): void {
		$registry = $this->registry_with( new StubTool( 'create_product', ApiScope::ProductsWrite, ToolGroup::Products ) );

		$response = $this->server( $registry )->handle(
			$this->request( 'tools/call', [ 'name' => 'create_product' ] ),
			AgentFactory::with_scopes( [ 'products:read' ] )
		);

		$this->assertSame( JsonRpcErrorCode::InvalidParams->value, $response->payload['error']['code'] );
		$this->assertStringContainsString( 'Unknown tool', $response->payload['error']['message'] );
	}

	public function test_schema_defaults_are_applied_before_the_tool_runs(): void {
		$tool = new StubTool(
			'create_product',
			ApiScope::ProductsWrite,
			ToolGroup::Products,
			[
				'type'       => 'object',
				'properties' => [
					'status' => [
						'type'    => 'string',
						'default' => 'draft',
					],
				],
			]
		);

		$response = $this->server( $this->registry_with( $tool ) )->handle(
			$this->request(
				'tools/call',
				[
					'name'      => 'create_product',
					'arguments' => [ 'name' => 'Widget' ],
				]
			),
			AgentFactory::with_scopes( [ 'products:write' ] )
		);

		$this->assertFalse( $response->payload['result']['isError'] );
		$this->assertSame(
			'draft',
			$tool->received['status'],
			'Without applied defaults a create call would inherit WooCommerce publish semantics.'
		);
	}

	public function test_an_explicit_argument_beats_the_schema_default(): void {
		$tool = new StubTool(
			'create_product',
			ApiScope::ProductsWrite,
			ToolGroup::Products,
			[
				'type'       => 'object',
				'properties' => [ 'status' => [ 'default' => 'draft' ] ],
			]
		);

		$this->server( $this->registry_with( $tool ) )->handle(
			$this->request(
				'tools/call',
				[
					'name'      => 'create_product',
					'arguments' => [ 'status' => 'publish' ],
				]
			),
			AgentFactory::with_scopes( [ 'products:write' ] )
		);

		$this->assertSame( 'publish', $tool->received['status'] );
	}

	public function test_a_tool_failure_is_a_result_not_a_protocol_error(): void {
		$tool = new StubTool(
			'list_products',
			ApiScope::ProductsRead,
			ToolGroup::Products,
			[ 'type' => 'object' ],
			new ToolCallException( 'WooCommerce rejected the request.' )
		);

		$response = $this->server( $this->registry_with( $tool ) )->handle(
			$this->request( 'tools/call', [ 'name' => 'list_products' ] ),
			AgentFactory::with_scopes( [ 'products:read' ] )
		);

		$this->assertArrayNotHasKey( 'error', $response->payload );
		$this->assertTrue( $response->payload['result']['isError'] );
		$this->assertSame( 'WooCommerce rejected the request.', $response->payload['result']['content'][0]['text'] );
	}

	public function test_both_success_and_tool_failure_are_audited(): void {
		$ok = new StubTool( 'list_products', ApiScope::ProductsRead, ToolGroup::Products );
		$this->server( $this->registry_with( $ok ) )->handle(
			$this->request( 'tools/call', [ 'name' => 'list_products' ] ),
			AgentFactory::with_scopes( [ 'products:read' ] )
		);

		$failing = new StubTool(
			'list_products',
			ApiScope::ProductsRead,
			ToolGroup::Products,
			[ 'type' => 'object' ],
			new ToolCallException( 'boom' )
		);
		$this->server( $this->registry_with( $failing ) )->handle(
			$this->request( 'tools/call', [ 'name' => 'list_products' ] ),
			AgentFactory::with_scopes( [ 'products:read' ] )
		);

		$this->assertCount( 2, $this->logged );
		$this->assertFalse( $this->logged[0][2] );
		$this->assertTrue( $this->logged[1][2] );
	}

	/**
	 * A bug in a tool is still a tool call. It used to escape the handler as an
	 * opaque protocol error and take the audit row with it, which is exactly the
	 * call an administrator most needs to see afterwards.
	 */
	public function test_an_unexpected_throwable_is_a_result_and_is_audited(): void {
		$response = $this->server( $this->registry_with( $this->broken_tool() ) )->handle(
			$this->request( 'tools/call', [ 'name' => 'list_products' ] ),
			AgentFactory::with_scopes( [ 'products:read' ] )
		);

		$this->assertArrayNotHasKey( 'error', $response->payload );
		$this->assertTrue( $response->payload['result']['isError'] );
		$this->assertCount( 1, $this->logged );
		$this->assertTrue( $this->logged[0][2] );
	}

	/** Stack traces and file paths are for the store's log, not for the caller. */
	public function test_the_agent_is_not_told_the_internals_of_a_crash(): void {
		$response = $this->server( $this->registry_with( $this->broken_tool() ) )->handle(
			$this->request( 'tools/call', [ 'name' => 'list_products' ] ),
			AgentFactory::with_scopes( [ 'products:read' ] )
		);

		$this->assertStringNotContainsString( 'Cannot assign null', $response->payload['result']['content'][0]['text'] );
	}

	public function test_the_crash_itself_reaches_the_woocommerce_log(): void {
		$logger = new class() {
			/** @var list<string> */
			public array $errors = [];

			public function error( string $message, array $context = [] ): void {
				$this->errors[] = $message;
			}
		};

		Functions\when( 'wc_get_logger' )->justReturn( $logger );

		$this->server( $this->registry_with( $this->broken_tool() ) )->handle(
			$this->request( 'tools/call', [ 'name' => 'list_products' ] ),
			AgentFactory::with_scopes( [ 'products:read' ] )
		);

		$this->assertCount( 1, $logger->errors );
		$this->assertStringContainsString( 'list_products', $logger->errors[0] );
		$this->assertStringContainsString( 'RuntimeException', $logger->errors[0] );
		$this->assertStringContainsString( 'Cannot assign null', $logger->errors[0] );
	}

	private function broken_tool(): StubTool {
		return new StubTool(
			'list_products',
			ApiScope::ProductsRead,
			ToolGroup::Products,
			[ 'type' => 'object' ],
			new \RuntimeException( 'Cannot assign null to parameter $id of type int' )
		);
	}
}

/**
 * Stand-in for wp_json_encode() that the Brain\Monkey alias can delegate to
 * without recursing into itself.
 */
function wp_json_encode_stub( mixed $data ): string|false {
	return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test double for wp_json_encode() itself.
}
