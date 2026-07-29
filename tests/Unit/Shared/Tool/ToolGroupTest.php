<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Shared\Tool;

use AgentGateMcp\Shared\Tool\ToolGroup;
use AgentGateMcp\Tests\Unit\TestCase;

/**
 * ToolGroup values are not cosmetic: they are the prefix of every ApiScope and
 * of every agmcp_settings option key, so renaming a case silently orphans
 * stored settings on existing installs.
 */
final class ToolGroupTest extends TestCase {

	public function test_group_values_are_stable(): void {
		$this->assertSame(
			[
				'products',
				'orders',
				'customers',
				'reports',
				'coupons',
				'taxonomy',
				'variations',
				'reviews',
				'refunds',
				'shipping',
				'taxes',
				'data',
				'gateways',
				'settings',
				'content',
				'system',
			],
			array_map( static fn ( ToolGroup $group ): string => $group->value, ToolGroup::cases() )
		);
	}

	/**
	 * The upgrade promise: a release that adds groups must not turn any of them
	 * on. An install that enabled only Products keeps exposing only Products.
	 */
	public function test_every_group_added_after_the_first_release_ships_disabled(): void {
		$originals = [ ToolGroup::Products, ToolGroup::Orders, ToolGroup::Reports ];

		foreach ( ToolGroup::cases() as $group ) {
			if ( in_array( $group, $originals, true ) ) {
				continue;
			}

			$this->assertFalse( $group->enabled_by_default(), $group->value . ' ships enabled.' );
		}
	}

	/** @dataProvider all_groups */
	public function test_group_value_is_usable_as_an_option_key_prefix( ToolGroup $group ): void {
		$this->assertMatchesRegularExpression( '/^[a-z][a-z0-9_]*$/', $group->value );
	}

	/**
	 * label(), description(), noun() and section() are exhaustive match()
	 * expressions with no default arm, so `composer run analyse` already fails
	 * on a group that forgets one. This covers the runtime half: that the arms
	 * carry real text rather than a placeholder someone meant to come back to.
	 *
	 * @dataProvider all_groups
	 */
	public function test_every_group_carries_its_own_wording( ToolGroup $group ): void {
		$this->assertNotSame( '', $group->label() );
		$this->assertNotSame( '', $group->description() );
		$this->assertNotSame( '', $group->noun() );
	}

	/**
	 * The noun is interpolated into scope labels ("Read products"), so a group
	 * that returns its heading here would produce "Read Products" on the
	 * consent screen. Distinct strings is the whole reason noun() exists.
	 *
	 * @dataProvider all_groups
	 */
	public function test_noun_reads_mid_sentence( ToolGroup $group ): void {
		$this->assertSame( strtolower( $group->noun() ), $group->noun() );
	}

	/** @dataProvider all_groups */
	public function test_option_keys_are_derived_from_the_group_value( ToolGroup $group ): void {
		$this->assertSame( $group->value . '_read', $group->read_option_key() );
		$this->assertSame( $group->value . '_write', $group->write_option_key() );
	}

	/** @dataProvider all_groups */
	public function test_read_scope_exists_and_matches_the_group( ToolGroup $group ): void {
		$this->assertSame( $group->value . ':read', $group->read_scope()->value );
		$this->assertSame( $group, $group->read_scope()->group() );
	}

	/** @dataProvider all_groups */
	public function test_has_write_agrees_with_write_scope( ToolGroup $group ): void {
		$this->assertSame( null !== $group->write_scope(), $group->has_write() );
	}

	/**
	 * Phase 2 reshaped how these are computed; the shipped posture must not
	 * have moved with them. Customers stays off because it exposes personal
	 * data, and every write axis ships off regardless of group.
	 */
	public function test_shipped_read_defaults_are_unchanged(): void {
		$this->assertTrue( ToolGroup::Products->enabled_by_default() );
		$this->assertTrue( ToolGroup::Orders->enabled_by_default() );
		$this->assertTrue( ToolGroup::Reports->enabled_by_default() );
		$this->assertFalse( ToolGroup::Customers->enabled_by_default() );
	}

	/**
	 * The chat default is the ~14 tools the plugin shipped with, which is a
	 * different question from what the store exposes to outside apps — Customers
	 * is closed to the world but present in an admin's own chat.
	 */
	public function test_the_chat_default_is_the_four_everyday_groups(): void {
		$this->assertSame(
			[ ToolGroup::Products, ToolGroup::Orders, ToolGroup::Customers, ToolGroup::Reports ],
			array_values( array_filter( ToolGroup::cases(), static fn ( ToolGroup $group ): bool => $group->in_chat_by_default() ) )
		);
	}

	/**
	 * New groups stay out of the prompt until someone decides they earn a place
	 * in it, so an upgrade never silently widens what one chat message carries.
	 */
	public function test_a_group_added_later_is_out_of_the_chat_by_default(): void {
		$this->assertFalse( ToolGroup::Coupons->in_chat_by_default() );
	}

	/** @return iterable<string, array{ToolGroup}> */
	public static function all_groups(): iterable {
		foreach ( ToolGroup::cases() as $group ) {
			yield $group->value => [ $group ];
		}
	}
}
