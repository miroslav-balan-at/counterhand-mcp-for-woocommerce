<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Infrastructure\RestResult;
use Counterhand\Tests\Unit\TestCase;

final class RestResultTest extends TestCase {

	public function test_a_list_payload_is_a_collection(): void {
		$result = new RestResult( [ [ 'id' => 1 ], [ 'id' => 2 ] ] );

		$this->assertTrue( $result->is_collection() );
		$this->assertSame( [ [ 'id' => 1 ], [ 'id' => 2 ] ], $result->items() );
	}

	public function test_a_keyed_payload_is_a_single_resource(): void {
		$result = new RestResult( [ 'id' => 1 ] );

		$this->assertFalse( $result->is_collection() );
		$this->assertSame( [ 'id' => 1 ], $result->item() );
	}

	public function test_items_drops_non_array_entries_and_reindexes(): void {
		$result = new RestResult( [ [ 'id' => 1 ], 'junk', [ 'id' => 2 ] ] );

		$this->assertSame( [ [ 'id' => 1 ], [ 'id' => 2 ] ], $result->items() );
	}

	/** The wc/v3 report endpoints wrap their single object in a collection. */
	public function test_item_unwraps_a_single_object_wrapped_in_a_collection(): void {
		$result = new RestResult( [ [ 'total_sales' => '120.00' ] ] );

		$this->assertSame( [ 'total_sales' => '120.00' ], $result->item() );
	}

	public function test_item_of_an_empty_collection_is_an_empty_array(): void {
		$this->assertSame( [], ( new RestResult( [] ) )->item() );
	}

	public function test_pagination_reports_both_totals(): void {
		$result = new RestResult( [], 137, 14 );

		$this->assertSame(
			[
				'total'       => 137,
				'total_pages' => 14,
			],
			$result->pagination()
		);
	}

	/** Unpaginated endpoints send no headers; the keys must not appear at all. */
	public function test_pagination_is_empty_when_woocommerce_reported_no_totals(): void {
		$this->assertSame( [], ( new RestResult( [ 'id' => 1 ] ) )->pagination() );
	}

	public function test_pagination_keeps_a_genuine_zero_total(): void {
		$this->assertSame(
			[
				'total'       => 0,
				'total_pages' => 0,
			],
			( new RestResult( [], 0, 0 ) )->pagination()
		);
	}
}
