<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\OAuth\View;

use Counterhand\Features\OAuth\View\ConsentScopes;
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

	/** @param list<ApiScope> $offered */
	private function rows( array $offered ): array {
		$groups = [];

		foreach ( ConsentScopes::from( $offered )->sections as $section ) {
			foreach ( $section->groups as $group ) {
				$groups[ $group->group->value ] = $group;
			}
		}

		return $groups;
	}

	public function test_a_groups_two_scopes_land_on_one_row(): void {
		$groups = $this->rows( [ ApiScope::ProductsRead, ApiScope::ProductsWrite ] );

		$this->assertCount( 1, $groups );
		$this->assertSame( ApiScope::ProductsRead, $groups['products']->read );
		$this->assertSame( ApiScope::ProductsWrite, $groups['products']->write );
	}

	public function test_an_unrequested_axis_is_absent_rather_than_shown_unticked(): void {
		$groups = $this->rows( [ ApiScope::ProductsRead ] );

		$this->assertSame( ApiScope::ProductsRead, $groups['products']->read );
		$this->assertNull( $groups['products']->write );
		$this->assertFalse( $groups['products']->has_write() );
	}

	public function test_a_group_nobody_asked_about_gets_no_row(): void {
		$this->assertArrayNotHasKey( 'orders', $this->rows( [ ApiScope::ProductsRead ] ) );
	}

	public function test_a_section_with_nothing_in_it_is_not_rendered(): void {
		$sections = ConsentScopes::from( [ ApiScope::ProductsRead ] )->sections;

		$this->assertCount( 1, $sections );
		$this->assertSame( ToolGroup::Products->section(), $sections[0]->section );
	}

	/**
	 * The security property of this screen. An advanced group can change how the
	 * store charges money, so requesting it must not be enough to have it
	 * granted — the admin has to reach in and tick it.
	 */
	public function test_advanced_groups_are_never_pre_ticked(): void {
		foreach ( ConsentScopes::from( ApiScope::cases() )->sections as $section ) {
			foreach ( $section->groups as $group ) {
				$this->assertSame(
					! $section->section->is_advanced(),
					$group->pre_checked(),
					$group->group->value . ' is pre-ticked against its section.'
				);
			}
		}
	}

	public function test_advanced_sections_render_collapsed_and_others_do_not(): void {
		foreach ( ConsentScopes::from( ApiScope::cases() )->sections as $section ) {
			$this->assertSame( $section->section->is_advanced(), $section->is_collapsed() );
		}
	}

	public function test_a_write_anywhere_raises_the_warning(): void {
		$this->assertTrue( ConsentScopes::from( [ ApiScope::ProductsWrite ] )->has_write() );
		$this->assertFalse( ConsentScopes::from( [ ApiScope::ProductsRead, ApiScope::ReportsRead ] )->has_write() );
	}

	public function test_asking_for_nothing_renders_nothing(): void {
		$this->assertSame( [], ConsentScopes::from( [] )->sections );
	}
}
