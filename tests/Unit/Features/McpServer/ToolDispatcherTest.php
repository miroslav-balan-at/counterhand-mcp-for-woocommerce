<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\McpServer;

use Counterhand\Features\McpServer\DispatchStatus;
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

	/** @return array{0: ToolDispatcher, 1: StubTool} */
	private function gated_dispatcher(): array {
		$this->dispatcher( [ 'type' => 'object' ] );

		$tool     = new StubTool( 'gated_tool', ApiScope::ProductsRead, ToolGroup::Products, [ 'type' => 'object' ], confirms: true );
		$registry = new ToolRegistry( new PluginSettings() );
		$registry->add( $tool );

		return [ new ToolDispatcher( $registry ), $tool ];
	}

	/**
	 * The gate is the whole of what stands between an agent and a maintenance
	 * routine on a live store, whose endpoint every token owner already holds the
	 * capability for. Anything but boolean true is not a confirmation.
	 *
	 * @dataProvider not_confirmations
	 * @param array<string, mixed> $arguments
	 */
	public function test_a_gated_tool_is_not_reached_without_confirmation( array $arguments ): void {
		[ $dispatcher, $tool ] = $this->gated_dispatcher();

		$outcome = $dispatcher->dispatch( 'gated_tool', $arguments, AgentFactory::with_scopes( [ 'products:read' ] ) );

		$this->assertSame( DispatchStatus::Failed, $outcome->status );
		$this->assertStringContainsString( 'confirm', $outcome->message );
		$this->assertSame( 0, $tool->calls );
	}

	/** @return iterable<string, array{array<string, mixed>}> */
	public static function not_confirmations(): iterable {
		yield 'absent' => [ [] ];
		yield 'false' => [ [ 'confirm' => false ] ];
		yield 'truthy' => [ [ 'confirm' => 'yes' ] ];
	}

	public function test_a_confirmed_call_runs_without_handing_confirm_to_the_tool(): void {
		[ $dispatcher, $tool ] = $this->gated_dispatcher();

		$dispatcher->dispatch(
			'gated_tool',
			[
				'code'    => 'x',
				'confirm' => true,
			],
			AgentFactory::with_scopes( [ 'products:read' ] )
		);

		$this->assertSame( 1, $tool->calls );
		$this->assertSame( [ 'code' => 'x' ], $tool->received );
	}

	public function test_a_gated_tool_publishes_confirm_as_a_required_argument_over_any_schema(): void {
		[ $dispatcher ] = $this->gated_dispatcher();

		$schema = $dispatcher->published_schema(
			new StubTool(
				'empty_tool',
				ApiScope::ProductsRead,
				ToolGroup::Products,
				[
					'type'       => 'object',
					'properties' => [],
				],
				confirms: true
			)
		);

		$this->assertSame( 'boolean', $schema['properties']['confirm']['type'] );
		$this->assertContains( 'confirm', $schema['required'] );
	}

	public function test_an_ordinary_tool_publishes_its_own_schema_untouched(): void {
		[ $dispatcher, $tool ] = $this->dispatcher( [ 'type' => 'object' ] );

		$this->assertSame( [ 'type' => 'object' ], $dispatcher->published_schema( $tool ) );
	}

	/** Core's sanitizer throws on an object, so only the published form may hold one. */
	public function test_only_the_published_schema_turns_an_empty_list_into_an_object(): void {
		[ $dispatcher, $tool ] = $this->dispatcher(
			[
				'type'       => 'object',
				'properties' => [],
			]
		);

		$this->assertInstanceOf( \stdClass::class, $dispatcher->published_schema( $tool )['properties'] );
		$this->assertSame( [], $tool->input_schema()['properties'] );
	}

	/** An exception from third-party code is a failed call, with its audit row. */
	public function test_a_throw_during_validation_is_a_failed_call_not_a_protocol_error(): void {
		[ $dispatcher ] = $this->dispatcher( [ 'type' => 'object' ] );
		Functions\when( 'rest_sanitize_value_from_schema' )->alias(
			static function (): never {
				throw new \Error( 'Cannot use object of type stdClass as array' );
			}
		);

		$outcome = $dispatcher->dispatch( 'a_tool', [], AgentFactory::with_scopes( [ 'products:read' ] ) );

		$this->assertSame( DispatchStatus::Failed, $outcome->status );
	}

	/**
	 * @dataProvider shapes
	 * @param array<string, mixed> $property
	 */
	public function test_only_a_wrong_shape_is_refused_before_sanitizing( array $property, mixed $value, bool $reaches_the_tool ): void {
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof \WP_Error );

		$tool = $this->dispatch(
			[
				'type'       => 'object',
				'properties' => [ 'field' => $property ],
			],
			[ 'field' => $value ]
		);

		$this->assertSame( $reaches_the_tool ? 1 : 0, $tool->calls );
	}

	/** @return iterable<string, array{array<string, mixed>, mixed, bool}> */
	public static function shapes(): iterable {
		yield 'an object for a string, once stored as the word Array' => [ [ 'type' => 'string' ], [ 'content' => 'x' ], false ];
		yield 'a string for an object, once a blanked mail' => [ [ 'type' => 'object' ], 'send it to sales', false ];
		yield 'an empty string for an object' => [ [ 'type' => 'object' ], '', false ];
		yield 'a list for a string, nested' => [
			[
				'type'       => 'object',
				'properties' => [ 'recipient' => [ 'type' => 'string' ] ],
			],
			[ 'recipient' => [ 'a@example.test' ] ],
			false,
		];
		yield 'a number for a string, as a model sends a price' => [ [ 'type' => 'string' ], 19.99, true ];
		yield 'a number for a string id' => [ [ 'type' => 'string' ], 5, true ];
		yield 'a numeric string for an integer' => [ [ 'type' => 'integer' ], '5', true ];
		yield 'a comma-separated string for a list, which core reads as one' => [ [ 'type' => 'array' ], '1,2,3', true ];
		yield 'null for anything' => [ [ 'type' => 'string' ], null, true ];
		yield 'a string where either fits' => [ [ 'type' => [ 'string', 'object' ] ], 'x', true ];
		yield 'an undescribed field' => [ [], [ 'x' => 1 ], true ];
	}

	/** Models add stray arguments to calls that are otherwise right. */
	public function test_a_closed_schema_still_drops_what_it_does_not_name(): void {
		$tool = $this->dispatch(
			[
				'type'                 => 'object',
				'properties'           => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
			[
				'id'     => 5,
				'status' => 'draft',
			]
		);

		$this->assertSame( [ 'id' => 5 ], $tool->received );
	}

	public function test_a_per_property_required_is_published_as_a_list_at_any_depth(): void {
		[ $dispatcher ] = $this->dispatcher( [ 'type' => 'object' ] );

		$schema = $dispatcher->published_schema(
			new StubTool(
				'a_form_tool',
				ApiScope::ProductsRead,
				ToolGroup::Products,
				[
					'type'       => 'object',
					'required'   => [ 'form_id' ],
					'properties' => [
						'form_id' => [
							'type'     => 'integer',
							'required' => true,
						],
						'optional' => [
							'type'     => 'string',
							'required' => false,
						],
						'nested'   => [
							'type'       => 'object',
							'properties' => [
								'inner' => [
									'type'     => 'string',
									'required' => true,
								],
							],
						],
						'rows'     => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'key' => [
										'type'     => 'string',
										'required' => true,
									],
								],
							],
						],
						'either'   => [
							'anyOf' => [
								[
									'type'     => 'string',
									'required' => false,
								],
							],
						],
						'map'      => [
							'type'                 => 'object',
							'additionalProperties' => [
								'type'     => 'string',
								'required' => true,
							],
						],
					],
				]
			)
		);

		$this->assertSame( [ 'form_id' ], $schema['required'] );
		$this->assertArrayNotHasKey( 'required', $schema['properties']['form_id'] );
		$this->assertArrayNotHasKey( 'required', $schema['properties']['optional'] );
		$this->assertSame( [ 'inner' ], $schema['properties']['nested']['required'] );
		$this->assertArrayNotHasKey( 'required', $schema['properties']['nested']['properties']['inner'] );
		$this->assertSame( [ 'key' ], $schema['properties']['rows']['items']['required'] );
		$this->assertArrayNotHasKey( 'required', $schema['properties']['rows']['items']['properties']['key'] );
		$this->assertArrayNotHasKey( 'required', $schema['properties']['either']['anyOf'][0] );
		$this->assertArrayNotHasKey( 'required', $schema['properties']['map']['additionalProperties'] );
	}
}
