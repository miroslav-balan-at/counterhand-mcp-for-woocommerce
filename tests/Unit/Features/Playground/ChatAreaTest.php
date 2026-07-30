<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Playground;

use Counterhand\Features\Playground\ChatArea;
use Counterhand\Features\Playground\ChatToolPolicy;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Unit\TestCase;

/**
 * The rule the Chat picker exists to make visible: a tick is a request, and the
 * store's own settings decide whether it is granted. Getting this wrong is what
 * made every area appear enabled while twelve of them did nothing.
 */
final class ChatAreaTest extends TestCase {

	private function policy( bool $read, bool $write ): ChatToolPolicy {
		return new class( $read, $write ) implements ChatToolPolicy {
			public function __construct( private bool $read, private bool $write ) {}

			public function allows_read( ToolGroup $group ): bool {
				return $this->read;
			}

			public function allows_write( ToolGroup $group ): bool {
				return $this->write;
			}

			public function settings_url(): string {
				return 'https://store.example/wp-admin/admin.php?page=counterhand-mcp-settings';
			}
		};
	}

	public function test_a_ticked_area_the_store_withholds_is_flagged_as_overruled(): void {
		$area = ChatArea::of( ToolGroup::Coupons, [ ToolGroup::Coupons ], $this->policy( false, false ), 5 );

		$this->assertTrue( $area->is_selected );
		$this->assertTrue( $area->is_overruled_by_store(), 'A tick the store overrules has to be visible as such.' );
	}

	public function test_an_area_the_store_exposes_is_not_overruled(): void {
		$area = ChatArea::of( ToolGroup::Products, [ ToolGroup::Products ], $this->policy( true, true ), 5 );

		$this->assertFalse( $area->is_overruled_by_store() );
		$this->assertFalse( $area->is_read_only_by_store() );
	}

	/**
	 * The half-granted case, which is the default for every group: reads on,
	 * writes off. Silence here is what makes an agent look broken to someone who
	 * ticked "Orders" expecting it to be able to change one.
	 */
	public function test_reads_without_writes_is_reported_as_read_only(): void {
		$area = ChatArea::of( ToolGroup::Orders, [ ToolGroup::Orders ], $this->policy( true, false ), 7 );

		$this->assertFalse( $area->is_overruled_by_store() );
		$this->assertTrue( $area->is_read_only_by_store() );
	}

	/** A read-only group has no write axis to withhold, so it is not "read-only by store". */
	public function test_a_group_with_no_write_axis_is_not_reported_as_read_only(): void {
		$area = ChatArea::of( ToolGroup::Reports, [ ToolGroup::Reports ], $this->policy( true, false ), 2 );

		$this->assertFalse( $area->is_read_only_by_store() );
	}

	/** Nothing is said about an area the user did not ask for. */
	public function test_an_unticked_area_is_not_reported_at_all(): void {
		$area = ChatArea::of( ToolGroup::Coupons, [], $this->policy( false, false ), 5 );

		$this->assertFalse( $area->is_selected );
		$this->assertFalse( $area->is_read_only_by_store() );
	}

	public function test_it_carries_the_tool_count_so_the_panel_can_say_what_is_withheld(): void {
		$area = ChatArea::of( ToolGroup::Shipping, [ ToolGroup::Shipping ], $this->policy( false, false ), 14 );

		$this->assertSame( 14, $area->tool_count );
	}
}
