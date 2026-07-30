<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Tokens\Domain;

use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
use Counterhand\Features\Tokens\Domain\ScopeSummary;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Unit\TestCase;

/**
 * What a connection's row in wp-admin says it can reach. The summary is the only
 * place a store owner sees an existing grant after the consent screen is gone,
 * so understating one is worse than showing a long list.
 */
final class ScopeSummaryTest extends TestCase {

	private function summary( string ...$values ): ScopeSummary {
		return ScopeSummary::of( GrantedScopeSet::from_values( $values ) );
	}

	public function test_a_read_only_grant_is_one_grant_that_is_not_writable(): void {
		$summary = $this->summary( 'products:read' );

		$this->assertCount( 1, $summary->grants );
		$this->assertSame( ToolGroup::Products, $summary->grants[0]->group );
		$this->assertFalse( $summary->grants[0]->writable );
	}

	/** The whole point: two scopes over one group read as one thing. */
	public function test_both_axes_of_one_group_collapse_into_a_single_grant(): void {
		$summary = $this->summary( 'products:read', 'products:write' );

		$this->assertCount( 1, $summary->grants );
		$this->assertTrue( $summary->grants[0]->writable );
	}

	/**
	 * A write without its read is a legal grant — nothing forces a client to ask
	 * for both — and it must not vanish from the summary just because the read
	 * half is missing.
	 */
	public function test_a_write_without_its_read_still_appears(): void {
		$summary = $this->summary( 'orders:write' );

		$this->assertCount( 1, $summary->grants );
		$this->assertSame( ToolGroup::Orders, $summary->grants[0]->group );
		$this->assertTrue( $summary->grants[0]->writable );
	}

	public function test_a_group_with_no_granted_scope_is_absent(): void {
		$this->assertSame( [ ToolGroup::Products ], array_column( $this->summary( 'products:read' )->grants, 'group' ) );
	}

	public function test_an_empty_grant_summarises_to_nothing(): void {
		$this->assertTrue( $this->summary()->is_empty() );
		$this->assertSame( [], $this->summary()->labels() );
	}

	/**
	 * Catalogue order, not grant order, so the same token never reads two
	 * different ways and a reissued token does not look like it changed.
	 */
	public function test_grants_come_back_in_catalogue_order( ): void {
		$summary = $this->summary( 'coupons:read', 'products:read', 'orders:read' );

		$this->assertSame(
			[ ToolGroup::Products, ToolGroup::Orders, ToolGroup::Coupons ],
			array_column( $summary->grants, 'group' )
		);
	}

	public function test_the_tail_beyond_the_limit_is_counted_not_listed(): void {
		$summary = $this->summary( 'products:read', 'orders:read', 'customers:read', 'coupons:read' );

		$this->assertCount( 3, $summary->shown( 3 ) );
		$this->assertSame( 1, $summary->hidden( 3 ) );
	}

	public function test_a_short_grant_has_no_tail(): void {
		$summary = $this->summary( 'products:read' );

		$this->assertCount( 1, $summary->shown( 3 ) );
		$this->assertSame( 0, $summary->hidden( 3 ) );
	}

	/** The marker is what tells a read grant from a write one at a glance. */
	public function test_a_writable_grants_badge_is_marked_and_a_read_ones_is_not(): void {
		$read  = $this->summary( 'products:read' )->grants[0];
		$write = $this->summary( 'products:read', 'products:write' )->grants[0];

		$this->assertSame( ToolGroup::Products->label(), $read->badge() );
		$this->assertNotSame( $read->badge(), $write->badge() );
		$this->assertStringContainsString( ToolGroup::Products->label(), $write->badge() );
	}

	/** Reports is read-only in WooCommerce, so its grant can never be writable. */
	public function test_a_read_only_group_is_never_reported_as_writable(): void {
		$this->assertFalse( $this->summary( 'reports:read' )->grants[0]->writable );
	}
}
