<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\OAuth\View;

use Brain\Monkey\Functions;
use Counterhand\Features\OAuth\View\ConsentScopes;
use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Settings\PublishedScopes;
use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Unit\TestCase;

/**
 * The consent screen's layout, decided here rather than inside markup.
 *
 * This is the one screen where a store owner grants a stranger access to their
 * shop, and the only defence against approving too much is that the screen is
 * readable and that dangerous boxes start empty.
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

	public function test_a_groups_two_scopes_land_on_one_row(): void {
		$groups = $this->rows( [ ApiScope::ProductsRead, ApiScope::ProductsWrite ] );

		$this->assertCount( 1, $groups );
		$this->assertSame(
			[ ApiScope::ProductsRead, ApiScope::ProductsWrite ],
			array_map( static fn ( $row ) => $row->scope, $groups['products']->scopes )
		);
	}

	public function test_an_unrequested_axis_is_absent_rather_than_shown_unticked(): void {
		$groups = $this->rows( [ ApiScope::ProductsRead ] );

		$this->assertSame( [ ApiScope::ProductsRead ], array_map( static fn ( $row ) => $row->scope, $groups['products']->scopes ) );
	}

	public function test_a_group_nobody_asked_about_gets_no_row(): void {
		$this->assertArrayNotHasKey( 'orders', $this->rows( [ ApiScope::ProductsRead ] ) );
	}

	/**
	 * The change this screen exists for: a scope the store withholds stays on
	 * the screen, disabled, so the admin learns the area exists and where to
	 * switch it on — instead of meeting the gap later as a missing tool.
	 */
	public function test_a_withheld_scope_is_shown_disabled_rather_than_dropped(): void {
		$groups = $this->rows(
			[ ApiScope::ProductsRead, ApiScope::ProductsWrite ],
			[ 'products_write' => false ]
		);

		[ $read, $write ] = $groups['products']->scopes;

		$this->assertTrue( $read->available );
		$this->assertFalse( $write->available );
		$this->assertFalse( $write->pre_checked, 'A box that cannot be granted must not start ticked.' );
	}

	public function test_a_withheld_write_does_not_raise_the_change_warning(): void {
		$consent = $this->consent( [ ApiScope::ProductsWrite ], [ 'products_write' => false ] );

		$this->assertFalse( $consent->has_write() );
		$this->assertFalse( $consent->has_grantable() );
		$this->assertTrue( $consent->has_withheld() );
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

	public function test_a_grantable_write_anywhere_raises_the_warning(): void {
		$this->assertTrue( $this->consent( [ ApiScope::ProductsWrite ] )->has_write() );
		$this->assertFalse( $this->consent( [ ApiScope::ProductsRead, ApiScope::ReportsRead ] )->has_write() );
	}

	public function test_asking_for_nothing_renders_nothing(): void {
		$this->assertSame( [], $this->consent( [] )->sections );
	}
}
