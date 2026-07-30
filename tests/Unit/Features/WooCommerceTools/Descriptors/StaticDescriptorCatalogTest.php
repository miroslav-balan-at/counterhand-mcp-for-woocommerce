<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\WooCommerceTools\Descriptors\CouponDescriptors;
use Counterhand\Features\WooCommerceTools\Descriptors\StaticDescriptorCatalog;
use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * Integrity of the whole shipped surface, checked without WordPress present.
 *
 * The catalog is hand-edited and will grow to around a hundred operations, so
 * the risks are clerical: a name typed twice, an item operation on a resource
 * with no item route, a write on a group that has no write scope. Each of those
 * is a broken tool on a live store and none of them is visible by reading one
 * descriptor file. This is what makes editing the catalog safe.
 */
final class StaticDescriptorCatalogTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	/** @return list<ResourceDescriptor> */
	private function resources(): array {
		return ( new StaticDescriptorCatalog() )->resources();
	}

	/** @return list<array{ResourceDescriptor, OperationDescriptor}> */
	private function operations(): array {
		$pairs = [];

		foreach ( $this->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				$pairs[] = [ $resource, $operation ];
			}
		}

		return $pairs;
	}

	public function test_the_shipped_catalog_is_not_empty(): void {
		$this->assertNotSame( [], $this->operations() );
	}

	public function test_every_tool_name_is_claimed_once(): void {
		$names = array_map(
			static fn ( array $pair ): string => $pair[1]->name->value,
			$this->operations()
		);

		$this->assertSame( array_unique( $names ), $names );
	}

	/** ToolName enforces this on construction; this proves nothing bypassed it. */
	public function test_every_tool_name_fits_the_audit_log_column(): void {
		foreach ( $this->operations() as [, $operation] ) {
			$this->assertMatchesRegularExpression( '/^[a-z][a-z0-9_]{0,63}$/', $operation->name->value );
		}
	}

	public function test_every_operation_has_a_route_to_dispatch_to(): void {
		foreach ( $this->operations() as [$resource, $operation] ) {
			// Throws when an item operation is declared without an item route.
			$route = $resource->route_for( $operation->operation );

			$this->assertNotSame( '', $route->path_template() );
		}
	}

	public function test_every_operation_has_a_scope_its_group_can_grant(): void {
		foreach ( $this->operations() as [$resource, $operation] ) {
			$intent = $operation->operation->intent();

			$this->assertNotNull(
				$intent->scope_of( $resource->group ),
				sprintf( 'Tool "%s" is a %s on a group that cannot grant it.', $operation->name->value, $intent->value )
			);
		}
	}

	/**
	 * An item route needs a placeholder to say which one it means — unless the
	 * resource is addressed as a whole, in which case there is nothing to say.
	 *
	 * Two shapes rely on that exception, and both are WooCommerce's doing: a
	 * shipping zone's location set is read and replaced at one path, and the
	 * current currency is a singleton at a fixed path. Both declare the same
	 * route for collection and item, and that equality is what marks them as
	 * whole-resource rather than as an item route missing its id.
	 */
	public function test_every_item_route_names_which_item_unless_there_is_only_one(): void {
		foreach ( $this->resources() as $resource ) {
			if ( null === $resource->item || $resource->item->template === $resource->collection->template ) {
				continue;
			}

			$this->assertNotSame( [], $resource->item->parameters(), $resource->id );
		}
	}

	/**
	 * The collection must sit at or above the item route, never below it.
	 *
	 * What this is really defending is that the permission probe — which asks
	 * about the collection, with placeholders unbound — is asking about the
	 * resource as a whole rather than about one row it cannot name. There is no
	 * structural way to assert that directly, because whether a callback needs
	 * an id is a property of WooCommerce's controller rather than of the URL.
	 * Two simpler proxies were tried and both were wrong: "no placeholder at
	 * all" fails order notes at /orders/{order_id}/notes, and "must not end in a
	 * placeholder" fails settings at /settings/{group_id}, which is a genuine
	 * collection gated by an id-free manager check.
	 *
	 * What is left is the shape mistake worth catching: a descriptor that names
	 * an item route as its collection, so the probe asks about one row. Equality
	 * is allowed, for the resources WooCommerce addresses only as a whole — a
	 * shipping zone's location set is read and replaced at one path.
	 *
	 * The real invariant is checked against a running WooCommerce instead, by
	 * confirming every probe answers true for an administrator.
	 */
	public function test_no_collection_route_sits_below_its_item_route(): void {
		foreach ( $this->resources() as $resource ) {
			if ( null === $resource->item ) {
				continue;
			}

			$this->assertStringStartsWith(
				$resource->collection->template,
				$resource->item->template,
				$resource->id . ' probes a route more specific than the one it dispatches to.'
			);
		}
	}

	public function test_every_tool_describes_itself(): void {
		foreach ( $this->operations() as [$resource, $operation] ) {
			$description = $operation->describe( $resource->singular, $resource->plural );

			$this->assertNotSame( '', $description );
			$this->assertStringEndsWith( '.', $description );
		}
	}

	public function test_every_resource_names_itself_in_both_numbers(): void {
		foreach ( $this->resources() as $resource ) {
			$this->assertNotSame( '', $resource->id );
			$this->assertNotSame( '', $resource->singular );
			$this->assertNotSame( '', $resource->plural );
		}
	}

	/**
	 * Two resources answering under one key would silently overwrite each
	 * other's tools once the catalog grows past a single provider.
	 */
	public function test_every_resource_id_is_claimed_once(): void {
		$ids = array_map( static fn ( ResourceDescriptor $r ): string => $r->id, $this->resources() );

		$this->assertSame( array_unique( $ids ), $ids );
	}

	public function test_coupons_ship_the_five_operations_techspawn_exposes(): void {
		$names = array_map(
			static fn ( OperationDescriptor $operation ): string => $operation->name->value,
			( new CouponDescriptors() )->resources()[0]->operations
		);

		$this->assertSame(
			[ 'get_coupons', 'get_coupon', 'create_coupon', 'update_coupon', 'delete_coupon' ],
			$names
		);
	}

	public function test_a_custom_provider_replaces_the_shipped_set(): void {
		$catalog = new StaticDescriptorCatalog( $this->provider() );

		$this->assertCount( 1, $catalog->resources() );
		$this->assertSame( 'things', $catalog->resources()[0]->id );
	}

	/**
	 * The extension point third-party WooCommerce plugins hang their own
	 * endpoints on.
	 */
	public function test_the_filter_can_add_a_resource(): void {
		$extra = $this->provider()->resources()[0];

		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value ): mixed => [ ...$value, $extra ]
		);

		$ids = array_map( static fn ( ResourceDescriptor $r ): string => $r->id, $this->resources() );

		$this->assertContains( 'things', $ids );
	}

	/** A filter returning junk must not put junk in front of an agent. */
	public function test_the_filter_cannot_introduce_a_non_descriptor(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value ): mixed => [ ...$value, 'not a descriptor', null ]
		);

		$this->assertContainsOnlyInstancesOf( ResourceDescriptor::class, $this->resources() );
	}

	private function provider(): DescriptorProvider {
		return new class() implements DescriptorProvider {

			public function resources(): array {
				return [
					new ResourceDescriptor(
						'things',
						ToolGroup::Products,
						RestRoute::wc( '/things' ),
						null,
						'thing',
						'things',
						[
							new OperationDescriptor(
								ToolName::from( 'get_things' ),
								Operation::GetItems,
								FieldProfile::everything()
							),
						]
					),
				];
			}
		};
	}
}
