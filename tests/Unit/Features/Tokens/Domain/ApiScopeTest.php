<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Tokens\Domain;

use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Unit\TestCase;

/**
 * label() derives its wording from the scope's group, curating only the scopes
 * the mechanical phrasing gets wrong. These tests pin the exact strings the
 * consent screen shows, so the derivation cannot quietly reword a grant an
 * store owner is being asked to approve.
 */
final class ApiScopeTest extends TestCase {

	public function test_values_returns_every_case_as_a_string(): void {
		$this->assertSame(
			[
				'products:read',
				'products:write',
				'orders:read',
				'orders:write',
				'customers:read',
				'reports:read',
				'coupons:read',
				'coupons:write',
				'taxonomy:read',
				'taxonomy:write',
				'variations:read',
				'variations:write',
				'reviews:read',
				'reviews:write',
				'refunds:read',
				'refunds:write',
				'shipping:read',
				'shipping:write',
				'taxes:read',
				'taxes:write',
				'data:read',
				'gateways:read',
				'gateways:write',
				'settings:read',
				'settings:write',
				'content:read',
				'content:write',
				'system:read',
				'system:write',
			],
			ApiScope::values()
		);
	}

	/**
	 * The set a client that named no scopes is offered. Getting this wrong is
	 * how "I connected an app and it could rewrite my tax settings" happens, so
	 * it is asserted as a property rather than as a list that would need editing
	 * every time a group is added.
	 */
	public function test_the_no_scope_default_grants_no_writes(): void {
		foreach ( ApiScope::conservative_default() as $scope ) {
			$this->assertFalse( $scope->is_write(), $scope->value . ' must not be offered to a client that asked for nothing.' );
		}
	}

	public function test_the_no_scope_default_skips_advanced_sections(): void {
		foreach ( ApiScope::conservative_default() as $scope ) {
			$this->assertFalse(
				$scope->group()->section()->is_advanced(),
				$scope->value . ' is advanced and must be asked for explicitly.'
			);
		}
	}

	/** An empty default would silently break every client that omits `scope`. */
	public function test_the_no_scope_default_is_not_empty(): void {
		$this->assertNotSame( [], ApiScope::conservative_default() );
	}

	/** @dataProvider all_scopes */
	public function test_every_scope_has_a_label_and_a_description( ApiScope $scope ): void {
		$this->assertNotSame( '', $scope->label() );
		$this->assertNotSame( '', $scope->description() );
	}

	/** @dataProvider all_scopes */
	public function test_is_write_agrees_with_the_scope_suffix( ApiScope $scope ): void {
		$this->assertSame( str_ends_with( $scope->value, ':write' ), $scope->is_write() );
	}

	/**
	 * The group prefix is load-bearing: PluginSettings derives its option keys
	 * from ToolGroup values, and ToolRegistry pairs scopes with groups.
	 *
	 * @dataProvider all_scopes
	 */
	public function test_every_scope_is_prefixed_by_a_known_tool_group( ApiScope $scope ): void {
		[ $prefix ] = explode( ':', $scope->value );

		$this->assertNotNull(
			ToolGroup::tryFrom( $prefix ),
			sprintf( 'Scope "%s" has no matching ToolGroup.', $scope->value )
		);
	}

	public function test_every_tool_group_has_at_least_a_read_scope(): void {
		foreach ( ToolGroup::cases() as $group ) {
			$this->assertNotNull(
				ApiScope::tryFrom( $group->value . ':read' ),
				sprintf( 'Group "%s" has no :read scope.', $group->value )
			);
		}
	}

	/**
	 * The exact consent-screen wording. All but one come from the derived path
	 * rather than a hand-written table, so this is what proves derivation
	 * produces the sentences a store owner actually reads.
	 */
	public function test_labels_are_the_wording_shown_on_the_consent_screen(): void {
		$this->assertSame(
			[
				'products:read'  => 'Read products',
				'products:write' => 'Manage products',
				'orders:read'    => 'Read orders',
				'orders:write'   => 'Update orders',
				'customers:read' => 'Read customers',
				'reports:read'   => 'Read reports',
				'coupons:read'   => 'Read coupons',
				'coupons:write'  => 'Manage coupons',

				// Everything below is derived, which is the point: adding a
				// group costs no wording unless the derived phrasing misleads.
				'taxonomy:read'    => 'Read categories and tags',
				'taxonomy:write'   => 'Manage categories and tags',
				'variations:read'  => 'Read variations',
				'variations:write' => 'Manage variations',
				'reviews:read'     => 'Read reviews',
				'reviews:write'    => 'Manage reviews',
				'refunds:read'     => 'Read refunds',
				'refunds:write'    => 'Manage refunds',
				'shipping:read'    => 'Read shipping',
				'shipping:write'   => 'Manage shipping',
				'taxes:read'       => 'Read taxes',
				'taxes:write'      => 'Manage taxes',
				'data:read'        => 'Read reference data',
				'gateways:read'    => 'Read payment gateways',
				'gateways:write'   => 'Manage payment gateways',
				'settings:read'    => 'Read store settings',
				'settings:write'   => 'Manage store settings',
				'content:read'     => 'Read posts and pages',
				'content:write'    => 'Manage posts and pages',
				'system:read'      => 'Read system tools',
				'system:write'     => 'Manage system tools',
			],
			array_combine(
				ApiScope::values(),
				array_map( static fn ( ApiScope $scope ): string => $scope->label(), ApiScope::cases() )
			)
		);
	}

	/**
	 * orders:write is the one scope whose derived label would overpromise, so
	 * it is curated. If derivation ever starts producing "Update orders" on its
	 * own, the curated entry is dead weight and should go.
	 */
	public function test_orders_write_is_curated_rather_than_derived(): void {
		$this->assertSame( 'Update orders', ApiScope::OrdersWrite->label() );
		$this->assertNotSame( 'Manage orders', ApiScope::OrdersWrite->label() );
	}

	/** @dataProvider all_scopes */
	public function test_group_round_trips_through_the_scope_axis( ApiScope $scope ): void {
		$group = $scope->group();

		$this->assertSame(
			$scope,
			$scope->is_write() ? $group->write_scope() : $group->read_scope(),
			sprintf( 'Scope "%s" does not round-trip through its own group.', $scope->value )
		);
	}

	/**
	 * has_write() is what the settings tab uses to decide whether to render a
	 * write checkbox at all, so it has to agree with the scope catalog rather
	 * than with a hardcoded list of read-only groups.
	 */
	public function test_has_write_agrees_with_the_scope_catalog(): void {
		foreach ( ToolGroup::cases() as $group ) {
			$this->assertSame(
				null !== ApiScope::tryFrom( $group->value . ':write' ),
				$group->has_write(),
				sprintf( 'Group "%s" disagrees with the scope catalog on writes.', $group->value )
			);
		}
	}

	/** @return iterable<string, array{ApiScope}> */
	public static function all_scopes(): iterable {
		foreach ( ApiScope::cases() as $scope ) {
			yield $scope->value => [ $scope ];
		}
	}
}
