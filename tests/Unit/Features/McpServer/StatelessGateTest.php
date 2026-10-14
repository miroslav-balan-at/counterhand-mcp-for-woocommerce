<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\McpServer;

use Counterhand\Features\McpServer\McpHttpRequest;
use Counterhand\Features\McpServer\StatelessGate;
use Counterhand\Shared\JsonRpc\JsonRpcRequest;
use Counterhand\Tests\Unit\TestCase;

/**
 * Which era a request belongs to, and what refuses a stateless one. The rules
 * come from the 2026-07-28 specification and the official conformance suite.
 */
final class StatelessGateTest extends TestCase {

	private const MODERN = '2026-07-28';

	private StatelessGate $gate;

	protected function setUp(): void {
		parent::setUp();

		$this->gate = new StatelessGate();
	}

	/** @param array<string, mixed> $params */
	private function request( string $method, array $params = [], bool $notification = false ): JsonRpcRequest {
		$envelope = [
			'jsonrpc' => '2.0',
			'method'  => $method,
			'params'  => $params,
		];
		if ( ! $notification ) {
			$envelope['id'] = 7;
		}

		return JsonRpcRequest::from_body( (string) json_encode( $envelope ) );
	}

	/** @return array<string, mixed> */
	private static function meta( string $version = self::MODERN, bool $capabilities = true ): array {
		$meta = [ 'io.modelcontextprotocol/protocolVersion' => $version ];
		if ( $capabilities ) {
			$meta['io.modelcontextprotocol/clientCapabilities'] = [];
		}

		return [ '_meta' => $meta ];
	}

	/** @param array<string, string> $headers */
	private static function http( array $headers = [] ): McpHttpRequest {
		return new McpHttpRequest( 'POST', '', $headers );
	}

	/** @return array<string, string> */
	private static function headers_for( string $method, ?string $name = null ): array {
		$headers = [
			'MCP-Protocol-Version' => self::MODERN,
			'Mcp-Method'           => $method,
		];
		if ( null !== $name ) {
			$headers['Mcp-Name'] = $name;
		}

		return $headers;
	}

	public function test_a_request_without_a_claim_is_legacy(): void {
		$this->assertFalse( $this->gate->is_stateless( $this->request( 'tools/list' ), self::http() ) );
	}

	/** 2025-06-18 and later clients already send the header on every request, with their own version. */
	public function test_a_legacy_header_alone_does_not_make_a_request_stateless(): void {
		$this->assertFalse( $this->gate->is_stateless( $this->request( 'tools/list' ), self::http( [ 'MCP-Protocol-Version' => '2025-11-25' ] ) ) );
	}

	public function test_initialize_without_a_claim_is_legacy_whatever_the_header_says(): void {
		$this->assertFalse( $this->gate->is_stateless( $this->request( 'initialize' ), self::http( [ 'MCP-Protocol-Version' => self::MODERN ] ) ) );
	}

	public function test_a_modern_claim_makes_a_request_stateless(): void {
		$this->assertTrue( $this->gate->is_stateless( $this->request( 'tools/list', self::meta() ), self::http() ) );
	}

	public function test_a_claim_naming_a_legacy_revision_is_served_as_legacy(): void {
		$this->assertFalse( $this->gate->is_stateless( $this->request( 'tools/list', self::meta( '2025-11-25' ) ), self::http() ) );
	}

	/** Its envelope is missing, so it is refused as a malformed stateless request rather than served as legacy. */
	public function test_a_modern_header_without_a_claim_is_stateless(): void {
		$this->assertTrue( $this->gate->is_stateless( $this->request( 'tools/list' ), self::http( [ 'MCP-Protocol-Version' => self::MODERN ] ) ) );
	}

	public function test_a_complete_matching_request_passes(): void {
		$request = $this->request( 'tools/call', [ ...self::meta(), 'name' => 'list_products' ] );

		$this->assertNull( $this->gate->reject( $request, self::http( self::headers_for( 'tools/call', 'list_products' ) ) ) );
	}

	public function test_a_missing_claim_is_invalid_params_on_400(): void {
		$reply = $this->gate->reject( $this->request( 'tools/list' ), self::http( self::headers_for( 'tools/list' ) ) );

		$this->assertSame( 400, $reply?->status );
		$this->assertSame( -32602, $reply->body['error']['code'] );
		$this->assertSame( 7, $reply->body['id'], 'every error carries the request id' );
	}

	/** The conformance suite checks both members: the versions served, and the one asked for. */
	public function test_an_unsupported_version_names_what_is_supported_and_what_was_requested(): void {
		$reply = $this->gate->reject( $this->request( 'server/discover', self::meta( 'v999.0.0' ) ), self::http( [ 'MCP-Protocol-Version' => 'v999.0.0' ] ) );

		$this->assertSame( 400, $reply?->status );
		$this->assertSame( -32022, $reply->body['error']['code'] );
		$this->assertSame( [ self::MODERN ], $reply->body['error']['data']['supported'] );
		$this->assertSame( 'v999.0.0', $reply->body['error']['data']['requested'] );
	}

	/** The order the conformance suite asserts: a disagreeing header is a mismatch before the version is judged. */
	public function test_a_header_mismatch_is_reported_before_an_unsupported_version(): void {
		$reply = $this->gate->reject( $this->request( 'server/discover', self::meta( 'v999.0.0' ) ), self::http( [ 'MCP-Protocol-Version' => self::MODERN ] ) );

		$this->assertSame( 400, $reply?->status );
		$this->assertSame( -32020, $reply->body['error']['code'] );
	}

	public function test_missing_client_capabilities_is_invalid_params(): void {
		$reply = $this->gate->reject( $this->request( 'tools/list', self::meta( capabilities: false ) ), self::http( self::headers_for( 'tools/list' ) ) );

		$this->assertSame( -32602, $reply?->body['error']['code'] );
	}

	/** @dataProvider mismatched_headers */
	public function test_headers_that_disagree_with_the_body_are_refused( array $headers ): void {
		$reply = $this->gate->reject( $this->request( 'tools/call', [ ...self::meta(), 'name' => 'list_products' ] ), self::http( $headers ) );

		$this->assertSame( 400, $reply?->status );
		$this->assertSame( -32020, $reply->body['error']['code'] );
	}

	/** @return \Generator<string, array{array<string, string>}> */
	public static function mismatched_headers(): \Generator {
		yield 'no headers'            => [ [] ];
		yield 'version disagrees'     => [ [ ...self::headers_for( 'tools/call', 'list_products' ), 'MCP-Protocol-Version' => '2025-11-25' ] ];
		yield 'method disagrees'      => [ self::headers_for( 'tools/list', 'list_products' ) ];
		yield 'name missing'          => [ self::headers_for( 'tools/call' ) ];
		yield 'name disagrees'        => [ self::headers_for( 'tools/call', 'delete_product' ) ];
		yield 'name badly encoded'    => [ self::headers_for( 'tools/call', '=?base64?not base64!?=' ) ];
	}

	public function test_a_base64_encoded_name_is_decoded_before_it_is_compared(): void {
		$request = $this->request( 'tools/call', [ ...self::meta(), 'name' => 'Grüße' ] );
		$encoded = '=?base64?' . base64_encode( 'Grüße' ) . '?=';

		$this->assertNull( $this->gate->reject( $request, self::http( self::headers_for( 'tools/call', $encoded ) ) ) );
	}

	public function test_header_names_are_case_insensitive(): void {
		$request = $this->request( 'tools/list', self::meta() );

		$this->assertNull(
			$this->gate->reject(
				$request,
				self::http(
					[
						'mcp-protocol-version' => self::MODERN,
						'MCP-METHOD'           => 'tools/list',
					]
				)
			)
		);
	}

	public function test_a_notification_is_not_held_to_the_request_header_rules(): void {
		$this->assertNull( $this->gate->reject( $this->request( 'notifications/initialized', self::meta(), true ), self::http() ) );
	}
}
