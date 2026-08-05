<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\OAuth\View;

use Brain\Monkey\Functions;
use Counterhand\Features\OAuth\View\ConsentAvailability;
use Counterhand\Features\OAuth\View\ConsentScopes;
use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Settings\PublishedScopes;
use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Unit\TestCase;

/**
 * The consent screen's layout, decided here rather than inside markup.
 *
 * The screen shows the whole catalogue: what is grantable, what the store has
 * switched off, and what the app never asked for — each disabled box saying
 * why. This is the one screen where the admin is thinking about permissions,
 * so an area they could enable must be discoverable here rather than met later
 * as a missing tool.
 */
final class ConsentScopesTest extends TestCase {

	/** @param list<ApiScope> $requested */
	private function consent( array $requested, array $settings_overrides = [] ): ConsentScopes {
		$settings = [];
		foreach ( ApiScope::cases() as $scope ) {
			$settings[ $scope->group()->read_option_key() ]  = true;
			$settings[ $scope->group()->write_option_key() ] = true;
		}

		Functions\when( 'get_option' )->justReturn( array_merge( $settings, $settings_overrides ) );

		return ConsentScopes::from( $requested, new PublishedScopes( new PluginSettings() ) );
	}

	/** @param list<ApiScope> $requested */
	private function rows( array $requested, array $settings_overrides = [] ): array {
		$groups = [];

		foreach ( $this->consent( $requested, $settings_overrides )->sections as $section ) {
			foreach ( $section->groups as $group ) {
				$groups[ $group->group->value ] = $group;
			}
		}

		return $groups;
	}

	public function test_every_group_is_on_the_screen_regardless_of_the_request(): void {
		$this->assertCount( count( ToolGroup::cases() ), $this->rows( [ ApiScope::ProductsRead ] ) );
	}

	public function test_a_groups_two_scopes_land_on_one_row(): void {
		$groups = $this->rows( [ ApiScope::ProductsRead, ApiScope::ProductsWrite ] );

		$this->assertSame(
			[ ApiScope::ProductsRead, ApiScope::ProductsWrite ],
			array_map( static fn ( $row ) => $row->scope, $groups['products']->scopes )
		);
	}

	/**
	 * The reason this screen shows the catalogue: an area the store switched
	 * off is a disabled box the admin can act on, not a silent absence. A
	 * discovery-respecting client never requests such a scope, so keying the
	 * disabled rows to the request would render them never.
	 */
	public function test_a_switched_off_scope_is_shown_disabled_with_the_reason(): void {
		$groups = $this->rows(
			[ ApiScope::ProductsRead ],
			[
				'orders_read'  => false,
				'orders_write' => false,
			]
		);

		foreach ( $groups['orders']->scopes as $row ) {
			$this->assertSame( ConsentAvailability::SwitchedOff, $row->availability );
			$this->assertFalse( $row->pre_checked, 'A box that cannot be granted must not start ticked.' );
			$this->assertNotSame( '', $row->unavailable_reason() );
		}
	}

	public function test_an_unrequested_scope_is_disabled_for_its_own_reason(): void {
		$groups = $this->rows( [ ApiScope::ProductsRead ] );

		$this->assertSame( ConsentAvailability::Grantable, $groups['products']->scopes[0]->availability );
		$this->assertSame( ConsentAvailability::NotRequested, $groups['products']->scopes[1]->availability );
		$this->assertSame( ConsentAvailability::NotRequested, $groups['orders']->scopes[0]->availability );
	}

	public function test_settings_off_wins_over_not_requested_as_the_reason(): void {
		$groups = $this->rows( [], [ 'orders_read' => false ] );

		$this->assertSame(
			ConsentAvailability::SwitchedOff,
			$groups['orders']->scopes[0]->availability,
			'Off-in-settings is the actionable reason; not-requested would send the admin to the wrong fix.'
		);
	}

	public function test_only_a_grantable_write_raises_the_change_warning(): void {
		$this->assertTrue( $this->consent( [ ApiScope::ProductsWrite ] )->has_write() );
		$this->assertFalse( $this->consent( [ ApiScope::ProductsWrite ], [ 'products_write' => false ] )->has_write() );
		$this->assertFalse( $this->consent( [ ApiScope::ProductsRead ] )->has_write() );
	}

	public function test_the_settings_hint_keys_to_switched_off_rows_only(): void {
		$this->assertTrue( $this->consent( [ ApiScope::ProductsRead ], [ 'orders_read' => false ] )->has_withheld() );
		$this->assertFalse(
			$this->consent( [ ApiScope::ProductsRead ] )->has_withheld(),
			'Unrequested rows are not a Settings problem, so they must not raise the Settings hint.'
		);
	}

	public function test_nothing_grantable_is_detectable_for_the_no_approve_state(): void {
		$this->assertFalse( $this->consent( [] )->has_grantable() );
		$this->assertTrue( $this->consent( [ ApiScope::ProductsRead ] )->has_grantable() );
	}

	public function test_advanced_groups_are_never_pre_ticked(): void {
		foreach ( $this->consent( ApiScope::cases() )->sections as $section ) {
			foreach ( $section->groups as $group ) {
				foreach ( $group->scopes as $row ) {
					$this->assertSame(
						! $section->section->is_advanced(),
						$row->pre_checked,
						$row->scope->value . ' is pre-ticked against its section.'
					);
				}
			}
		}
	}

	public function test_advanced_sections_render_collapsed_and_others_do_not(): void {
		foreach ( $this->consent( ApiScope::cases() )->sections as $section ) {
			$this->assertSame( $section->section->is_advanced(), $section->is_collapsed() );
		}
	}
}
