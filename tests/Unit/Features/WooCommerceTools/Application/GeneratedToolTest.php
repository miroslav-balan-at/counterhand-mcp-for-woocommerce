<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Application;

use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Features\WooCommerceTools\Application\GeneratedTool;
use Counterhand\Features\WooCommerceTools\Domain\ArgumentPolicy;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Domain\Verdict;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestResult;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use Counterhand\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use Counterhand\Features\WooCommerceTools\Infrastructure\SchemaProvider;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Doubles\FakeRestGateway;
use Counterhand\Tests\Doubles\FakeRestServer;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The one execute() that stands in for a hundred hand-written ones.
 *
 * Everything asserted here is a promise made to every generated tool at once, so
 * these tests are worth more than their line count suggests: the dispatch is
 * exercised through a fake gateway and a literal route table, with no WordPress
 * and no WooCommerce present.
 */
final class GeneratedToolTest extends TestCase {

	private FakeRestGateway $gateway;

	protected function setUp(): void {
		parent::setUp();

		$this->gateway = new FakeRestGateway();

		Functions\when( 'did_action' )->justReturn( 1 );
	}

	/**
	 * A route table in the raw shape get_routes() returns, including core's
	 * regex spelling of the id placeholder — so the normalization descriptors
	 * depend on is exercised rather than assumed.
	 *
	 * @return array<string, mixed>
	 */
	private function routes( bool $permitted = true ): array {
		$callback = static fn (): bool => $permitted;

		return [
			'/wc/v3/coupons'               => [
				[
					'methods'             => [ 'GET' => true ],
					'args'                => [
						'page'    => [
							'type'    => 'integer',
							'default' => 1,
						],
						'search'  => [ 'type' => 'string' ],
						'context' => [ 'type' => 'string' ],
					],
					'permission_callback' => $callback,
				],
				[
					'methods'             => [ 'POST' => true ],
					'args'                => [
						'code'   => [
							'type'     => 'string',
							'required' => true,
						],
						'amount' => [ 'type' => 'string' ],
					],
					'permission_callback' => $callback,
				],
			],
			'/wc/v3/coupons/(?P<id>[\d]+)' => [
				[
					'methods'             => [
						'GET'    => true,
						'PUT'    => true,
						'DELETE' => true,
					],
					'args'                => [
						'id'    => [ 'type' => 'integer' ],
						'code'  => [ 'type' => 'string' ],
						'force' => [ 'type' => 'boolean' ],
					],
					'permission_callback' => $callback,
				],
			],
		];
	}

	/**
	 * @param array<string, mixed>|null $routes
	 * @param array<string, mixed>      $forced_params
	 * @param array<string, mixed>      $default_params
	 */
	private function tool(
		Operation $operation,
		FieldProfile $fields = new FieldProfile( [], [] ),
		string $hint = '',
		array $forced_params = [],
		?array $routes = null,
		bool $permitted = true,
		array $default_params = [],
		bool $requires_confirmation = false,
		?ArgumentPolicy $policy = null
	): GeneratedTool {
		Functions\when( 'rest_get_server' )->justReturn( new FakeRestServer( $routes ?? $this->routes( $permitted ) ) );

		$catalog = new RouteCatalog();

		return new GeneratedTool(
			$this->resource(),
			new OperationDescriptor( ToolName::from( 'a_tool' ), $operation, $fields, $hint, null, $forced_params, $default_params, $requires_confirmation, $policy ),
			ApiScope::CouponsRead,
			$this->gateway,
			$catalog,
			new RoutePermissionProbe( $catalog ),
			new SchemaProvider( $catalog )
		);
	}

	private function resource(): ResourceDescriptor {
		return new ResourceDescriptor(
			'coupons',
			ToolGroup::Coupons,
			RestRoute::wc( '/coupons' ),
			RestRoute::wc( '/coupons/{id}' ),
			'coupon',
			'coupons',
			[]
		);
	}

	public function test_the_tool_answers_with_its_descriptors_identity(): void {
		$tool = $this->tool( Operation::GetItems );

		$this->assertSame( 'a_tool', $tool->name() );
		$this->assertSame( ToolGroup::Coupons, $tool->group() );
		$this->assertSame( ApiScope::CouponsRead, $tool->required_scope() );
	}

	public function test_the_description_names_the_resource_and_carries_the_hint(): void {
		$description = $this->tool( Operation::CreateItem, hint: 'code must be unique.' )->description();

		$this->assertStringContainsString( 'coupon', $description );
		$this->assertStringEndsWith( 'code must be unique.', $description );
	}

	public function test_a_collection_operation_dispatches_to_the_collection_route(): void {
		$this->tool( Operation::GetItems )->execute( [] );

		$call = $this->gateway->call();

		$this->assertSame( '/wc/v3/coupons', $call['route']->path_template() );
		$this->assertSame( RestMethod::Get, $call['method'] );
	}

	public function test_an_item_operation_dispatches_to_the_item_route(): void {
		$this->tool( Operation::UpdateItem )->execute( [ 'id' => 7 ] );

		$call = $this->gateway->call();

		$this->assertSame( '/wc/v3/coupons/{id}', $call['route']->path_template() );
		$this->assertSame( RestMethod::Put, $call['method'] );
		$this->assertSame( 7, $call['params']['id'] );
	}

	public function test_the_agents_arguments_are_passed_through(): void {
		$this->tool( Operation::GetItems )->execute(
			[
				'search'   => 'summer',
				'per_page' => 5,
			]
		);

		$params = $this->gateway->call()['params'];

		$this->assertSame( 'summer', $params['search'] );
		$this->assertSame( 5, $params['per_page'] );
	}

	/**
	 * 'edit' context returns raw HTML for every prose field and roughly doubles
	 * the payload, and nothing on this side renders an edit form.
	 */
	public function test_view_context_is_forced_and_cannot_be_argued_out_of(): void {
		$this->tool( Operation::GetItems )->execute( [ 'context' => 'edit' ] );

		$this->assertSame( 'view', $this->gateway->call()['params']['context'] );
	}

	public function test_a_descriptor_can_pin_a_parameter_the_agent_cannot_override(): void {
		$this->tool( Operation::CreateItem, forced_params: [ 'status' => 'draft' ] )
			->execute(
				[
					'status' => 'publish',
					'code'   => 'x',
				]
			);

		$params = $this->gateway->call()['params'];

		$this->assertSame( 'draft', $params['status'] );
		$this->assertSame( 'x', $params['code'] );
	}

	/**
	 * The other half of forced_params: a value the agent may still override.
	 *
	 * This is what carries create_product's draft safety across the migration —
	 * WooCommerce's own default for a new product is publish, and forcing draft
	 * outright would leave no way to publish anything.
	 */
	public function test_a_descriptor_can_move_a_default_the_agent_may_still_override(): void {
		$this->tool( Operation::CreateItem, default_params: [ 'amount' => '0' ] )
			->execute( [ 'code' => 'x' ] );

		$this->assertSame( '0', $this->gateway->call()['params']['amount'] );
	}

	public function test_the_agent_wins_over_a_moved_default(): void {
		$this->tool( Operation::CreateItem, default_params: [ 'amount' => '0' ] )
			->execute(
				[
					'code'   => 'x',
					'amount' => '25',
				]
			);

		$this->assertSame( '25', $this->gateway->call()['params']['amount'] );
	}

	/**
	 * A moved default the agent is not told about would make the tool describe
	 * itself inaccurately — it would advertise WooCommerce's default and send
	 * another.
	 */
	public function test_a_moved_default_is_published_in_the_schema(): void {
		$schema = $this->tool( Operation::CreateItem, default_params: [ 'amount' => '0' ] )->input_schema();

		$this->assertSame( '0', $schema['properties']['amount']['default'] );
	}

	/** A default naming a field the route does not declare is dropped, not invented. */
	public function test_a_moved_default_for_an_unpublished_field_adds_no_property(): void {
		$schema = $this->tool( Operation::CreateItem, default_params: [ 'not_a_coupon_field' => 'x' ] )->input_schema();

		$this->assertArrayNotHasKey( 'not_a_coupon_field', $schema['properties'] );
	}

	/**
	 * The confirmation gate, which is the whole of what stands between an agent
	 * and a maintenance routine on a live store — WooCommerce gates that
	 * endpoint on a capability every token owner already holds.
	 */
	public function test_an_operation_needing_confirmation_refuses_without_it(): void {
		$tool = $this->tool( Operation::CreateItem, requires_confirmation: true );

		try {
			$tool->execute( [ 'code' => 'x' ] );
			$this->fail( 'An unconfirmed call was executed.' );
		} catch ( ToolCallException $e ) {
			$this->assertStringContainsString( 'confirm', $e->getMessage() );
		}

		$this->assertSame( [], $this->gateway->calls, 'An unconfirmed call reached WooCommerce.' );
	}

	/** Anything but boolean true is not a confirmation. */
	public function test_a_truthy_value_is_not_a_confirmation(): void {
		$this->expectException( ToolCallException::class );

		$this->tool( Operation::CreateItem, requires_confirmation: true )
			->execute( [ 'code' => 'x', 'confirm' => 'yes' ] );
	}

	public function test_a_confirmed_call_goes_through_without_sending_confirm_on(): void {
		$this->tool( Operation::CreateItem, requires_confirmation: true )
			->execute( [ 'code' => 'x', 'confirm' => true ] );

		$params = $this->gateway->call()['params'];

		$this->assertSame( 'x', $params['code'] );
		$this->assertArrayNotHasKey( 'confirm', $params, 'confirm was forwarded as an unknown WooCommerce parameter.' );
	}

	/**
	 * Published in the schema, not only in the prose: a model reads a required
	 * argument far more reliably than it reads a warning.
	 */
	public function test_confirmation_is_published_as_a_required_argument(): void {
		$schema = $this->tool( Operation::CreateItem, requires_confirmation: true )->input_schema();

		$this->assertSame( 'boolean', $schema['properties']['confirm']['type'] );
		$this->assertContains( 'confirm', $schema['required'] );
	}

	public function test_an_ordinary_operation_publishes_no_confirm_argument(): void {
		$schema = $this->tool( Operation::CreateItem )->input_schema();

		$this->assertArrayNotHasKey( 'confirm', $schema['properties'] );
	}

	/** A policy refusal must stop the call, not annotate it. */
	public function test_a_policy_refusal_prevents_the_dispatch(): void {
		$policy = new class() implements ArgumentPolicy {
			public function verdict( array $arguments ): Verdict {
				return Verdict::deny( 'Not that one.' );
			}
		};

		try {
			$this->tool( Operation::CreateItem, policy: $policy )->execute( [ 'code' => 'x' ] );
			$this->fail( 'A refused call was executed.' );
		} catch ( ToolCallException $e ) {
			$this->assertSame( 'Not that one.', $e->getMessage() );
		}

		$this->assertSame( [], $this->gateway->calls );
	}

	public function test_a_policy_that_allows_does_not_interfere(): void {
		$policy = new class() implements ArgumentPolicy {
			public function verdict( array $arguments ): Verdict {
				return Verdict::allow();
			}
		};

		$this->tool( Operation::CreateItem, policy: $policy )->execute( [ 'code' => 'x' ] );

		$this->assertSame( 'x', $this->gateway->call()['params']['code'] );
	}

	public function test_the_profiles_output_fields_are_requested_back(): void {
		$this->tool( Operation::GetItems, new FieldProfile( [], [ 'id', 'code' ] ) )->execute( [] );

		$this->assertSame( 'id,code', $this->gateway->call()['params']['_fields'] );
	}

	/** No output profile means no _fields param at all, not an empty one. */
	public function test_a_profile_that_prunes_nothing_asks_for_everything(): void {
		$this->tool( Operation::GetItems )->execute( [] );

		$this->assertArrayNotHasKey( '_fields', $this->gateway->call()['params'] );
	}

	public function test_a_collection_result_is_returned_under_the_plural_with_its_totals(): void {
		$this->gateway->will_return( new RestResult( [ [ 'id' => 1 ], [ 'id' => 2 ] ], 47, 5 ) );

		$this->assertSame(
			[
				'coupons'     => [ [ 'id' => 1 ], [ 'id' => 2 ] ],
				'count'       => 2,
				'total'       => 47,
				'total_pages' => 5,
			],
			$this->tool( Operation::GetItems )->execute( [] )
		);
	}

	/** An endpoint that reports no totals must not invent them. */
	public function test_a_collection_without_totals_reports_only_what_it_knows(): void {
		$this->gateway->will_return( new RestResult( [ [ 'id' => 1 ] ] ) );

		$this->assertSame(
			[
				'coupons' => [ [ 'id' => 1 ] ],
				'count'   => 1,
			],
			$this->tool( Operation::GetItems )->execute( [] )
		);
	}

	public function test_an_item_result_is_returned_under_the_singular(): void {
		$this->gateway->will_return( new RestResult( [ 'id' => 9 ] ) );

		$this->assertSame(
			[ 'coupon' => [ 'id' => 9 ] ],
			$this->tool( Operation::GetItem )->execute( [ 'id' => 9 ] )
		);
	}

	public function test_a_delete_returns_the_deleted_resource_not_a_list(): void {
		$this->gateway->will_return( new RestResult( [ 'id' => 9 ] ) );

		$this->assertArrayHasKey( 'coupon', $this->tool( Operation::DeleteItem )->execute( [ 'id' => 9 ] ) );
	}

	public function test_the_input_schema_is_read_off_the_live_route(): void {
		$schema = $this->tool( Operation::GetItems )->input_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertSame(
			[
				'type'    => 'integer',
				'default' => 1,
			],
			$schema['properties']['page']
		);
		$this->assertArrayHasKey( 'search', $schema['properties'] );
	}

	public function test_the_input_schema_hides_the_transport_envelope(): void {
		$schema = $this->tool( Operation::GetItems )->input_schema();

		$this->assertArrayNotHasKey( 'context', $schema['properties'] );
	}

	public function test_a_profile_narrows_the_published_arguments(): void {
		$schema = $this->tool( Operation::GetItems, new FieldProfile( [ 'search' ], [] ) )->input_schema();

		$this->assertSame( [ 'search' ], array_keys( $schema['properties'] ) );
	}

	/**
	 * WooCommerce reads the id out of the URL and so marks it optional. Coming
	 * the other way an agent has only the argument list to go on.
	 */
	public function test_the_path_parameter_is_published_as_required(): void {
		$schema = $this->tool( Operation::GetItem, new FieldProfile( [ 'code' ], [] ) )->input_schema();

		$this->assertArrayHasKey( 'id', $schema['properties'] );
		$this->assertSame( [ 'id' ], $schema['required'] );
	}

	public function test_a_required_woocommerce_argument_stays_required(): void {
		$schema = $this->tool( Operation::CreateItem )->input_schema();

		$this->assertSame( [ 'code' ], $schema['required'] );
	}

	/**
	 * A tool published against an endpoint this WooCommerce no longer serves
	 * could only ever 404, so it is hidden rather than advertised.
	 */
	public function test_a_missing_route_makes_the_tool_unavailable(): void {
		$this->assertFalse( $this->tool( Operation::GetItems, routes: [] )->is_available() );
	}

	public function test_a_missing_route_publishes_an_empty_schema_rather_than_failing(): void {
		$this->assertEquals(
			[
				'type'                 => 'object',
				'properties'           => new \stdClass(),
				'additionalProperties' => false,
			],
			$this->tool( Operation::GetItems, routes: [] )->input_schema()
		);
	}

	public function test_a_denied_permission_check_makes_the_tool_unavailable(): void {
		$this->assertFalse( $this->tool( Operation::GetItems, permitted: false )->is_available() );
	}

	public function test_a_served_and_permitted_route_makes_the_tool_available(): void {
		$this->assertTrue( $this->tool( Operation::GetItems )->is_available() );
	}

	/**
	 * The one thing this design cannot afford to get wrong: map_meta_cap()
	 * denies an item capability asked without an id, so probing /coupons/{id}
	 * would hide the tool from administrators. Read intent asks GET on the
	 * collection, write intent asks POST on it, whatever the tool dispatches.
	 */
	public function test_permission_is_asked_of_the_collection_whatever_the_tool_dispatches(): void {
		$asked = [];

		$routes = [
			'/wc/v3/coupons'               => [
				[
					'methods'             => [
						'GET'  => true,
						'POST' => true,
					],
					'args'                => [],
					'permission_callback' => static function ( \WP_REST_Request $request ) use ( &$asked ): bool {
						$asked[] = $request->get_method() . ' ' . $request->get_route();

						return true;
					},
				],
			],
			'/wc/v3/coupons/(?P<id>[\d]+)' => [
				[
					'methods'             => [
						'GET'    => true,
						'DELETE' => true,
					],
					'args'                => [],
					'permission_callback' => static function () use ( &$asked ): bool {
						$asked[] = 'item route';

						return true;
					},
				],
			],
		];

		$this->tool( Operation::GetItem, routes: $routes )->is_available();
		$this->tool( Operation::DeleteItem, routes: $routes )->is_available();

		$this->assertSame( [ 'GET /wc/v3/coupons', 'POST /wc/v3/coupons' ], $asked );
	}
}
