<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\WooCommerceTools\Descriptors;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Features\WooCommerceTools\Application\DescribeFieldsTool;
use AgentGateMcp\Features\WooCommerceTools\Application\StoreOverviewTool;
use AgentGateMcp\Features\WooCommerceTools\Descriptors\StaticDescriptorCatalog;
use AgentGateMcp\Features\WooCommerceTools\Domain\MetaOperation;
use AgentGateMcp\Features\WooCommerceTools\Domain\Operation;
use AgentGateMcp\Features\WooCommerceTools\Domain\ResourceDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ToolIntent;
use AgentGateMcp\Shared\Tool\ToolGroup;
use AgentGateMcp\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The published tool surface, pinned.
 *
 * This is the migration's safety net, and it deliberately pins less than the
 * whole of tools/list. Names, groups and scopes are a contract: a client has
 * them written into its prompts, a store owner has granted a token against
 * them, and moving one silently breaks somebody. Input schemas are the
 * opposite — they are now read off the running WooCommerce, so pinning them
 * here would pin the very thing this design exists to keep current, and would
 * need a live WooCommerce to evaluate at all.
 *
 * So: the contract is asserted, the schemas are left free, and the boundary
 * between the two is stated rather than assumed.
 */
final class ShippedSurfaceTest extends TestCase {

	/**
	 * Every tool the plugin ships, mapped to the group and scope it is gated by.
	 *
	 * A diff here is either a deliberate change to the published contract or a
	 * mistake, and both are worth stopping for. Regenerate it deliberately, by
	 * reading it off the catalogue, never by pasting whatever the failure
	 * printed — that turns the guard into an echo.
	 */
	private const SHIPPED = [
		'list_products'                  => [ 'products', 'products:read' ],
		'get_product'                    => [ 'products', 'products:read' ],
		'create_product'                 => [ 'products', 'products:write' ],
		'update_product'                 => [ 'products', 'products:write' ],
		'delete_product'                 => [ 'products', 'products:write' ],
		'get_product_meta'               => [ 'products', 'products:read' ],
		'set_product_meta'               => [ 'products', 'products:write' ],
		'delete_product_meta'            => [ 'products', 'products:write' ],
		'get_product_categories'         => [ 'taxonomy', 'taxonomy:read' ],
		'get_product_category'           => [ 'taxonomy', 'taxonomy:read' ],
		'create_product_category'        => [ 'taxonomy', 'taxonomy:write' ],
		'update_product_category'        => [ 'taxonomy', 'taxonomy:write' ],
		'delete_product_category'        => [ 'taxonomy', 'taxonomy:write' ],
		'get_product_tags'               => [ 'taxonomy', 'taxonomy:read' ],
		'get_product_tag'                => [ 'taxonomy', 'taxonomy:read' ],
		'create_product_tag'             => [ 'taxonomy', 'taxonomy:write' ],
		'update_product_tag'             => [ 'taxonomy', 'taxonomy:write' ],
		'delete_product_tag'             => [ 'taxonomy', 'taxonomy:write' ],
		'get_product_attributes'         => [ 'taxonomy', 'taxonomy:read' ],
		'get_product_attribute'          => [ 'taxonomy', 'taxonomy:read' ],
		'create_product_attribute'       => [ 'taxonomy', 'taxonomy:write' ],
		'update_product_attribute'       => [ 'taxonomy', 'taxonomy:write' ],
		'delete_product_attribute'       => [ 'taxonomy', 'taxonomy:write' ],
		'get_attribute_terms'            => [ 'taxonomy', 'taxonomy:read' ],
		'get_attribute_term'             => [ 'taxonomy', 'taxonomy:read' ],
		'create_attribute_term'          => [ 'taxonomy', 'taxonomy:write' ],
		'update_attribute_term'          => [ 'taxonomy', 'taxonomy:write' ],
		'delete_attribute_term'          => [ 'taxonomy', 'taxonomy:write' ],
		'get_shipping_classes'           => [ 'taxonomy', 'taxonomy:read' ],
		'get_shipping_class'             => [ 'taxonomy', 'taxonomy:read' ],
		'create_shipping_class'          => [ 'taxonomy', 'taxonomy:write' ],
		'update_shipping_class'          => [ 'taxonomy', 'taxonomy:write' ],
		'delete_shipping_class'          => [ 'taxonomy', 'taxonomy:write' ],
		'get_product_variations'         => [ 'variations', 'variations:read' ],
		'get_product_variation'          => [ 'variations', 'variations:read' ],
		'create_product_variation'       => [ 'variations', 'variations:write' ],
		'update_product_variation'       => [ 'variations', 'variations:write' ],
		'delete_product_variation'       => [ 'variations', 'variations:write' ],
		'get_product_reviews'            => [ 'reviews', 'reviews:read' ],
		'get_product_review'             => [ 'reviews', 'reviews:read' ],
		'create_product_review'          => [ 'reviews', 'reviews:write' ],
		'update_product_review'          => [ 'reviews', 'reviews:write' ],
		'delete_product_review'          => [ 'reviews', 'reviews:write' ],
		'list_orders'                    => [ 'orders', 'orders:read' ],
		'get_order'                      => [ 'orders', 'orders:read' ],
		'update_order_status'            => [ 'orders', 'orders:write' ],
		'get_order_meta'                 => [ 'orders', 'orders:read' ],
		'set_order_meta'                 => [ 'orders', 'orders:write' ],
		'delete_order_meta'              => [ 'orders', 'orders:write' ],
		'get_order_notes'                => [ 'orders', 'orders:read' ],
		'get_order_note'                 => [ 'orders', 'orders:read' ],
		'add_order_note'                 => [ 'orders', 'orders:write' ],
		'delete_order_note'              => [ 'orders', 'orders:write' ],
		'get_order_refunds'              => [ 'refunds', 'refunds:read' ],
		'get_order_refund'               => [ 'refunds', 'refunds:read' ],
		'create_order_refund'            => [ 'refunds', 'refunds:write' ],
		'delete_order_refund'            => [ 'refunds', 'refunds:write' ],
		'list_customers'                 => [ 'customers', 'customers:read' ],
		'get_customer'                   => [ 'customers', 'customers:read' ],
		'get_customer_meta'              => [ 'customers', 'customers:read' ],
		'get_sales_report'               => [ 'reports', 'reports:read' ],
		'get_top_sellers'                => [ 'reports', 'reports:read' ],
		'get_order_totals'               => [ 'reports', 'reports:read' ],
		'get_product_totals'             => [ 'reports', 'reports:read' ],
		'get_customer_totals'            => [ 'reports', 'reports:read' ],
		'get_coupon_totals'              => [ 'reports', 'reports:read' ],
		'get_review_totals'              => [ 'reports', 'reports:read' ],
		'get_coupons'                    => [ 'coupons', 'coupons:read' ],
		'get_coupon'                     => [ 'coupons', 'coupons:read' ],
		'create_coupon'                  => [ 'coupons', 'coupons:write' ],
		'update_coupon'                  => [ 'coupons', 'coupons:write' ],
		'delete_coupon'                  => [ 'coupons', 'coupons:write' ],
		'get_coupon_meta'                => [ 'coupons', 'coupons:read' ],
		'set_coupon_meta'                => [ 'coupons', 'coupons:write' ],
		'delete_coupon_meta'             => [ 'coupons', 'coupons:write' ],
		'get_shipping_zones'             => [ 'shipping', 'shipping:read' ],
		'get_shipping_zone'              => [ 'shipping', 'shipping:read' ],
		'create_shipping_zone'           => [ 'shipping', 'shipping:write' ],
		'update_shipping_zone'           => [ 'shipping', 'shipping:write' ],
		'delete_shipping_zone'           => [ 'shipping', 'shipping:write' ],
		'get_shipping_zone_locations'    => [ 'shipping', 'shipping:read' ],
		'update_shipping_zone_locations' => [ 'shipping', 'shipping:write' ],
		'get_shipping_zone_methods'      => [ 'shipping', 'shipping:read' ],
		'get_shipping_zone_method'       => [ 'shipping', 'shipping:read' ],
		'create_shipping_zone_method'    => [ 'shipping', 'shipping:write' ],
		'update_shipping_zone_method'    => [ 'shipping', 'shipping:write' ],
		'delete_shipping_zone_method'    => [ 'shipping', 'shipping:write' ],
		'get_shipping_methods'           => [ 'shipping', 'shipping:read' ],
		'get_shipping_method'            => [ 'shipping', 'shipping:read' ],
		'get_tax_rates'                  => [ 'taxes', 'taxes:read' ],
		'get_tax_rate'                   => [ 'taxes', 'taxes:read' ],
		'create_tax_rate'                => [ 'taxes', 'taxes:write' ],
		'update_tax_rate'                => [ 'taxes', 'taxes:write' ],
		'delete_tax_rate'                => [ 'taxes', 'taxes:write' ],
		'get_tax_classes'                => [ 'taxes', 'taxes:read' ],
		'create_tax_class'               => [ 'taxes', 'taxes:write' ],
		'delete_tax_class'               => [ 'taxes', 'taxes:write' ],
		'get_countries'                  => [ 'data', 'data:read' ],
		'get_country'                    => [ 'data', 'data:read' ],
		'get_continents'                 => [ 'data', 'data:read' ],
		'get_continent'                  => [ 'data', 'data:read' ],
		'get_currencies'                 => [ 'data', 'data:read' ],
		'get_currency'                   => [ 'data', 'data:read' ],
		'get_current_currency'           => [ 'data', 'data:read' ],
		'get_setting_groups'             => [ 'settings', 'settings:read' ],
		'get_settings'                   => [ 'settings', 'settings:read' ],
		'get_setting'                    => [ 'settings', 'settings:read' ],
		'update_setting'                 => [ 'settings', 'settings:write' ],
		'get_payment_gateways'           => [ 'gateways', 'gateways:read' ],
		'get_payment_gateway'            => [ 'gateways', 'gateways:read' ],
		'update_payment_gateway'         => [ 'gateways', 'gateways:write' ],
		'get_posts'                      => [ 'content', 'content:read' ],
		'get_post'                       => [ 'content', 'content:read' ],
		'create_post'                    => [ 'content', 'content:write' ],
		'update_post'                    => [ 'content', 'content:write' ],
		'delete_post'                    => [ 'content', 'content:write' ],
		'get_pages'                      => [ 'content', 'content:read' ],
		'get_page'                       => [ 'content', 'content:read' ],
		'create_page'                    => [ 'content', 'content:write' ],
		'update_page'                    => [ 'content', 'content:write' ],
		'delete_page'                    => [ 'content', 'content:write' ],
		'get_system_status'              => [ 'system', 'system:read' ],
		'get_system_status_tools'        => [ 'system', 'system:read' ],
		'get_system_status_tool'         => [ 'system', 'system:read' ],
		'run_system_status_tool'         => [ 'system', 'system:write' ],
		'get_store_overview'             => [ 'reports', 'reports:read' ],
		'describe_woocommerce_fields'    => [ 'products', 'products:read' ],
	];

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	/** @return list<ResourceDescriptor> */
	private function resources(): array {
		return ( new StaticDescriptorCatalog() )->resources();
	}

	/** @return array<string, array{string, string}> */
	private function surface(): array {
		$surface = [];

		foreach ( $this->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				$scope = $operation->operation->intent()->scope_of( $resource->group );

				$surface[ $operation->name->value ] = [ $resource->group->value, (string) $scope?->value ];
			}
		}

		foreach ( $this->resources() as $resource ) {
			if ( null === $resource->meta ) {
				continue;
			}

			foreach ( MetaOperation::cases() as $operation ) {
				$scope = $operation->intent()->scope_of( $resource->group );

				// A group with no write axis gets read-only meta, which is how
				// customer custom fields end up unwritable.
				if ( null === $scope ) {
					continue;
				}

				$surface[ $operation->tool_name( $resource->singular_slug() ) ] = [ $resource->group->value, $scope->value ];
			}
		}

		$overview                     = new StoreOverviewTool();
		$surface[ $overview->name() ] = [ $overview->group()->value, $overview->required_scope()->value ];

		$surface[ DescribeFieldsTool::NAME ] = [ ToolGroup::Products->value, ApiScope::ProductsRead->value ];

		return $surface;
	}

	public function test_the_shipped_surface_is_exactly_what_it_was(): void {
		$surface = $this->surface();

		ksort( $surface );
		$expected = self::SHIPPED;
		ksort( $expected );

		$this->assertSame( $expected, $surface );
	}

	/**
	 * Said out loud so a future edit that changes the count has to change the
	 * number too, rather than discovering later that a descriptor quietly
	 * stopped being registered.
	 */
	public function test_the_surface_is_one_hundred_and_twenty_seven_tools(): void {
		$this->assertCount( 127, $this->surface() );
	}

	/**
	 * Nothing outside the four original groups may ship enabled, so a store
	 * that upgrades into this release exposes exactly what it exposed before
	 * until someone ticks a new box.
	 */
	public function test_the_new_groups_expose_nothing_until_switched_on(): void {
		$arrived_enabled = array_filter(
			$this->surface(),
			static fn ( array $row ): bool => ToolGroup::from( $row[0] )->enabled_by_default()
				&& ! in_array( $row[0], [ 'products', 'orders', 'reports' ], true )
		);

		$this->assertSame( [], $arrived_enabled );
	}

	/**
	 * Every write tool must be reachable only through a :write scope. A write
	 * that slipped onto a read scope would be granted by a consent tick that
	 * says "Read ...".
	 */
	public function test_no_write_tool_is_gated_by_a_read_scope(): void {
		foreach ( $this->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				if ( ToolIntent::Write !== $operation->operation->intent() ) {
					continue;
				}

				$this->assertStringEndsWith(
					':write',
					(string) $operation->operation->intent()->scope_of( $resource->group )?->value,
					$operation->name->value . ' is a write on a read scope.'
				);
			}
		}
	}

	/**
	 * Names are the half of the contract a token cannot protect: a client calls
	 * them by string, and a rename reads to it as the tool having vanished.
	 */
	public function test_no_two_resources_claim_the_same_tool_name(): void {
		$names = [];

		foreach ( $this->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				$names[] = $operation->name->value;
			}
		}

		$this->assertSame( array_unique( $names ), $names );
	}

	/**
	 * A write declared on a read-only group has no scope to gate it, and the
	 * factory throws rather than build it. Catching that here means it is a
	 * failing test rather than a fatal on somebody's store.
	 */
	public function test_every_operation_has_a_scope_to_be_gated_by(): void {
		foreach ( $this->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				$this->assertNotNull(
					$operation->operation->intent()->scope_of( $resource->group ),
					$operation->name->value . ' has no scope on group ' . $resource->group->value
				);
			}
		}
	}

	/**
	 * route_for() throws for an item operation on a resource with no item
	 * route — true of the two report resources, which are collections with
	 * nothing behind them.
	 */
	public function test_no_resource_declares_an_item_operation_it_cannot_route(): void {
		foreach ( $this->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				$this->assertNotNull(
					$resource->route_for( $operation->operation ),
					$operation->name->value . ' cannot be routed.'
				);
			}
		}
	}

	/**
	 * Path placeholders are the one input a generated tool cannot get from the
	 * agent's schema by accident: the route cannot be bound without them, and
	 * an unbound one throws at dispatch.
	 */
	public function test_every_route_placeholder_is_a_name_woocommerce_would_recognise(): void {
		foreach ( $this->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				foreach ( $resource->route_for( $operation->operation )->parameters() as $parameter ) {
					$this->assertMatchesRegularExpression( '/^[a-z][a-z0-9_]*$/', $parameter );
				}
			}
		}
	}

	/** Every group that ships a tool must be one a store owner can actually toggle. */
	public function test_every_resource_belongs_to_a_real_group(): void {
		foreach ( $this->resources() as $resource ) {
			$this->assertContains( $resource->group, ToolGroup::cases() );
		}
	}

	/**
	 * The safety behaviour the migration had to carry across by hand, because
	 * WooCommerce's own default for a new product is publish. Losing it would
	 * put an agent's first draft on the shop front.
	 */
	public function test_a_created_product_defaults_to_draft(): void {
		foreach ( $this->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				if ( 'create_product' === $operation->name->value ) {
					$this->assertSame( Operation::CreateItem, $operation->operation );
					$this->assertSame( [ 'status' => 'draft' ], $operation->default_params );

					return;
				}
			}
		}

		$this->fail( 'create_product is no longer in the catalogue.' );
	}
}
