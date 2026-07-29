<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\WooCommerceTools\Application;

use AgentGateMcp\Features\WooCommerceTools\Application\DescribeFieldsTool;
use AgentGateMcp\Features\WooCommerceTools\Domain\DescriptorProvider;
use AgentGateMcp\Features\WooCommerceTools\Domain\FieldProfile;
use AgentGateMcp\Features\WooCommerceTools\Domain\Operation;
use AgentGateMcp\Features\WooCommerceTools\Domain\OperationDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ResourceDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ToolName;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestRoute;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\SchemaProvider;
use AgentGateMcp\Shared\Exception\ToolCallException;
use AgentGateMcp\Shared\Tool\ToolGroup;
use AgentGateMcp\Tests\Doubles\FakeRestServer;
use AgentGateMcp\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The escape hatch from field pruning.
 *
 * Generated tools publish a curated handful of the fields WooCommerce declares,
 * which is only a defensible trade while the rest stay findable. These tests are
 * that promise: what a tool hides is exactly what this reveals, and the two
 * halves are derived from the same route so they cannot drift apart.
 */
final class DescribeFieldsToolTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'did_action' )->justReturn( 1 );
	}

	private function routes(): array {
		return [
			'/wc/v3/coupons' => [
				[
					'methods'             => [ 'POST' => true ],
					'args'                => [
						'code'          => [
							'type'     => 'string',
							'required' => true,
						],
						'amount'        => [ 'type' => 'string' ],
						'description'   => [ 'type' => 'string' ],
						'usage_limit'   => [ 'type' => 'integer' ],
						'free_shipping' => [ 'type' => 'boolean' ],
						'context'       => [ 'type' => 'string' ],
					],
					'permission_callback' => static fn (): bool => true,
				],
			],
		];
	}

	private function tool( bool $allow_additional = true ): DescribeFieldsTool {
		Functions\when( 'rest_get_server' )->justReturn( new FakeRestServer( $this->routes() ) );

		$descriptors = new class( $allow_additional ) implements DescriptorProvider {
			public function __construct( private bool $allow_additional ) {}

			public function resources(): array {
				return [
					new ResourceDescriptor(
						'coupons',
						ToolGroup::Coupons,
						RestRoute::wc( '/coupons' ),
						RestRoute::wc( '/coupons/{id}' ),
						'coupon',
						'coupons',
						[
							new OperationDescriptor(
								ToolName::from( 'create_coupon' ),
								Operation::CreateItem,
								new FieldProfile( [ 'code', 'amount' ], [], $this->allow_additional )
							),
						]
					),
				];
			}
		};

		return new DescribeFieldsTool( $descriptors, new SchemaProvider( new RouteCatalog() ) );
	}

	public function test_it_identifies_itself(): void {
		$tool = $this->tool();

		$this->assertSame( 'describe_woocommerce_fields', $tool->name() );
		$this->assertTrue( $tool->is_available() );
		$this->assertSame( [ 'tool' ], $tool->input_schema()['required'] );
	}

	/**
	 * The whole point: the fields the curated tool leaves out come back here.
	 */
	public function test_it_reveals_the_fields_the_tool_prunes(): void {
		$result = $this->tool()->execute( [ 'tool' => 'create_coupon' ] );

		$this->assertSame( [ 'code', 'amount' ], $result['published'] );
		$this->assertSame(
			[ 'description', 'usage_limit', 'free_shipping' ],
			$result['additional']
		);
	}

	/** Published and additional together are the whole route, minus the envelope. */
	public function test_published_and_additional_account_for_every_field(): void {
		$result = $this->tool()->execute( [ 'tool' => 'create_coupon' ] );

		$this->assertSame(
			array_keys( $result['schema']['properties'] ),
			[ ...$result['published'], ...$result['additional'] ]
		);
		$this->assertArrayNotHasKey( 'context', $result['schema']['properties'] );
	}

	/** The full schema is WooCommerce's, types and required flags intact. */
	public function test_the_full_schema_carries_woocommerces_own_declarations(): void {
		$schema = $this->tool()->execute( [ 'tool' => 'create_coupon' ] )['schema'];

		$this->assertSame( 'integer', $schema['properties']['usage_limit']['type'] );
		$this->assertSame( 'boolean', $schema['properties']['free_shipping']['type'] );
		$this->assertContains( 'code', $schema['required'] );
	}

	/**
	 * Whether a revealed field can actually be sent is the difference between a
	 * useful answer and a cruel one, so it is reported rather than assumed.
	 */
	public function test_it_reports_whether_the_tool_accepts_the_extra_fields(): void {
		$this->assertTrue( $this->tool( true )->execute( [ 'tool' => 'create_coupon' ] )['accepts_extra'] );
		$this->assertFalse( $this->tool( false )->execute( [ 'tool' => 'create_coupon' ] )['accepts_extra'] );
	}

	public function test_it_names_the_route_it_described(): void {
		$result = $this->tool()->execute( [ 'tool' => 'create_coupon' ] );

		$this->assertSame( '/wc/v3/coupons', $result['route'] );
		$this->assertSame( 'POST', $result['method'] );
		$this->assertSame( 'coupons', $result['resource'] );
	}

	public function test_an_unknown_tool_is_refused_with_something_actionable(): void {
		$this->expectException( ToolCallException::class );
		$this->expectExceptionMessageMatches( '/exactly as it appears/' );

		$this->tool()->execute( [ 'tool' => 'get_store_overview' ] );
	}

	public function test_an_empty_tool_name_is_refused(): void {
		$this->expectException( ToolCallException::class );

		$this->tool()->execute( [ 'tool' => '  ' ] );
	}
}
