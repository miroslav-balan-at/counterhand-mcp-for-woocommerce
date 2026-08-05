<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\McpServer;

use Counterhand\Features\McpServer\ToolDispatcher;
use Counterhand\Features\McpServer\ToolRegistry;
use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Doubles\AgentFactory;
use Counterhand\Tests\Doubles\StubTool;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * What reaches a tool after the pipeline has had the arguments.
 *
 * The pipeline is where a request is shaped before WooCommerce ever sees it, so
 * a mistake here reads as WooCommerce rejecting a call the agent got right.
 */
final class ToolDispatcherTest extends TestCase {

	/** @return array{0: ToolDispatcher, 1: StubTool} */
	private function dispatcher( array $schema ): array {
		Functions\when( 'get_option' )->justReturn( [ 'products_read' => true ] );
		Functions\when( 'did_action' )->justReturn( 1 );

		// Identity stubs: WordPress' own sanitizer is not under test here, and
		// reimplementing it would test the reimplementation. What is under test
		// is the shaping this pipeline does before handing arguments over.
		Functions\when( 'rest_sanitize_value_from_schema' )->returnArg( 1 );
		Functions\when( 'rest_validate_value_from_schema' )->justReturn( true );

		$tool     = new StubTool( 'a_tool', ApiScope::ProductsRead, ToolGroup::Products, $schema );
		$registry = new ToolRegistry( new PluginSettings() );
		$registry->add( $tool );

		return [ new ToolDispatcher( $registry ), $tool ];
	}

	private function dispatch( array $schema, array $arguments ): StubTool {
		[ $dispatcher, $tool ] = $this->dispatcher( $schema );

		$dispatcher->dispatch( 'a_tool', $arguments, AgentFactory::with_scopes( [ 'products:read' ] ) );

		return $tool;
	}

	/**
	 * Models routinely send a structured argument as a JSON string and clients
	 * forward it verbatim. Rejecting that fails a call whose intent was right —
	 * create_product could not be given attributes at all.
	 */
	public function test_a_stringified_array_argument_is_parsed(): void {
		$tool = $this->dispatch(
			[
				'type'       => 'object',
				'properties' => [ 'attributes' => [ 'type' => 'array' ] ],
			],
			[ 'attributes' => '[{"id":5,"options":["S"]}]' ]
		);

		$this->assertSame(
			[
				[
					'id'      => 5,
					'options' => [ 'S' ],
				],
			],
			$tool->received['attributes']
		);
	}

	public function test_a_stringified_object_argument_is_parsed(): void {
		$tool = $this->dispatch(
			[
				'type'       => 'object',
				'properties' => [ 'dimensions' => [ 'type' => 'object' ] ],
			],
			[ 'dimensions' => '{"length":"10"}' ]
		);

		$this->assertSame( [ 'length' => '10' ], $tool->received['dimensions'] );
	}

	/**
	 * The guard that keeps the fix from becoming the opposite bug: the MCP
	 * Python SDK decoded any JSON-looking string and broke the tools that
	 * wanted one.
	 */
	public function test_a_string_argument_that_looks_like_json_is_left_alone(): void {
		$tool = $this->dispatch(
			[
				'type'       => 'object',
				'properties' => [ 'search' => [ 'type' => 'string' ] ],
			],
			[ 'search' => '[1,2]' ]
		);

		$this->assertSame( '[1,2]', $tool->received['search'] );
	}

	/** Left verbatim for WordPress' validator to refuse, rather than guessed at. */
	public function test_an_unparseable_string_is_left_untouched(): void {
		$tool = $this->dispatch(
			[
				'type'       => 'object',
				'properties' => [ 'attributes' => [ 'type' => 'array' ] ],
			],
			[ 'attributes' => 'not json' ]
		);

		$this->assertSame( 'not json', $tool->received['attributes'] );
	}

	/**
	 * WooCommerce declares default null on reviewer_email and format email on
	 * the same field, then rejects the null. It never applies absent defaults
	 * itself, so omitting the key is what its controllers expect — sending it
	 * made get_product_reviews fail on every call.
	 */
	public function test_a_null_default_is_omitted_rather_than_sent(): void {
		$tool = $this->dispatch(
			[
				'type'       => 'object',
				'properties' => [
					'reviewer_email' => [
						'type'    => 'string',
						'format'  => 'email',
						'default' => null,
					],
				],
			],
			[]
		);

		$this->assertArrayNotHasKey( 'reviewer_email', $tool->received );
	}

	public function test_a_real_default_is_still_applied(): void {
		$tool = $this->dispatch(
			[
				'type'       => 'object',
				'properties' => [
					'status' => [
						'type'    => 'string',
						'default' => 'draft',
					],
				],
			],
			[]
		);

		$this->assertSame( 'draft', $tool->received['status'] );
	}
}
