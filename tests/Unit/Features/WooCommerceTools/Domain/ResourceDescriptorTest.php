<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolIntent;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Unit\TestCase;

/**
 * Two routes and two nouns is the whole per-resource knowledge this plugin
 * holds. What it does with them decides where a tool dispatches and, more
 * importantly, which route gets asked whether the tool may be shown at all.
 */
final class ResourceDescriptorTest extends TestCase {

	private function resource( ?RestRoute $item = null, ?RestRoute $read_probe = null, ?RestRoute $write_probe = null ): ResourceDescriptor {
		return new ResourceDescriptor(
			'coupons',
			ToolGroup::Coupons,
			RestRoute::wc( '/coupons' ),
			$item,
			'coupon',
			'coupons',
			[],
			$read_probe,
			$write_probe
		);
	}

	public function test_a_collection_operation_uses_the_collection_route(): void {
		$resource = $this->resource( RestRoute::wc( '/coupons/{id}' ) );

		$this->assertSame( '/wc/v3/coupons', $resource->route_for( Operation::GetItems )->path_template() );
		$this->assertSame( '/wc/v3/coupons', $resource->route_for( Operation::CreateItem )->path_template() );
	}

	public function test_an_item_operation_uses_the_item_route(): void {
		$resource = $this->resource( RestRoute::wc( '/coupons/{id}' ) );

		foreach ( [ Operation::GetItem, Operation::UpdateItem, Operation::DeleteItem ] as $operation ) {
			$this->assertSame( '/wc/v3/coupons/{id}', $resource->route_for( $operation )->path_template() );
		}
	}

	/**
	 * Some wc/v3 resources are singletons with no item route at all. Declaring
	 * an item operation on one is a catalog bug, and it says so rather than
	 * quietly dispatching to the collection and updating everything.
	 */
	public function test_an_item_operation_on_a_resource_with_no_item_route_is_refused(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'coupons' );

		$this->resource()->route_for( Operation::UpdateItem );
	}

	/**
	 * map_meta_cap() answers do_not_allow for an item capability asked without
	 * an id, so probing /coupons/{id} would hide the resource from
	 * administrators. The collection is the only question that can be asked.
	 */
	public function test_permission_is_asked_of_the_collection_by_default(): void {
		$resource = $this->resource( RestRoute::wc( '/coupons/{id}' ) );

		$this->assertSame( '/wc/v3/coupons', $resource->probe_route( ToolIntent::Read )->path_template() );
		$this->assertSame( '/wc/v3/coupons', $resource->probe_route( ToolIntent::Write )->path_template() );
	}

	/** For the handful of resources with no id-free collection to ask. */
	public function test_each_intent_can_name_its_own_probe(): void {
		$resource = $this->resource(
			null,
			RestRoute::wc( '/system_status' ),
			RestRoute::wc( '/system_status/tools' )
		);

		$this->assertSame( '/wc/v3/system_status', $resource->probe_route( ToolIntent::Read )->path_template() );
		$this->assertSame( '/wc/v3/system_status/tools', $resource->probe_route( ToolIntent::Write )->path_template() );
	}

	public function test_one_intents_override_does_not_move_the_other(): void {
		$resource = $this->resource( null, null, RestRoute::wc( '/coupons/batch' ) );

		$this->assertSame( '/wc/v3/coupons', $resource->probe_route( ToolIntent::Read )->path_template() );
		$this->assertSame( '/wc/v3/coupons/batch', $resource->probe_route( ToolIntent::Write )->path_template() );
	}
}
