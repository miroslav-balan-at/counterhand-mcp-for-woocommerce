<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Shared\Tool;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\ToolGroup;
use AgentGateMcp\Shared\Tool\ToolSection;
use AgentGateMcp\Tests\Unit\TestCase;

/**
 * Sections decide how the settings tab and the OAuth consent screen are laid
 * out. A group that lands in no section is invisible on both — it can never be
 * switched on, and no one is told why.
 */
final class ToolSectionTest extends TestCase {

	/** @dataProvider all_sections */
	public function test_every_section_carries_its_own_wording( ToolSection $section ): void {
		$this->assertNotSame( '', $section->label() );
		$this->assertNotSame( '', $section->description() );
	}

	/**
	 * The partition property: every group appears in exactly one section, so
	 * rendering section by section shows the whole catalog and shows it once.
	 */
	public function test_sections_partition_every_tool_group(): void {
		$grouped = [];

		foreach ( ToolSection::cases() as $section ) {
			foreach ( $section->groups() as $group ) {
				$grouped[] = $group->value;
			}
		}

		sort( $grouped );
		$expected = array_map( static fn ( ToolGroup $group ): string => $group->value, ToolGroup::cases() );
		sort( $expected );

		$this->assertSame( $expected, $grouped );
	}

	/** @dataProvider all_sections */
	public function test_groups_only_returns_groups_that_claim_the_section( ToolSection $section ): void {
		$claiming = array_values(
			array_filter(
				$section->groups(),
				static fn ( ToolGroup $group ): bool => $section === $group->section()
			)
		);

		// Compared as a whole rather than asserted per group, so the empty
		// sections still assert something instead of passing vacuously.
		$this->assertSame( $section->groups(), $claiming );
	}

	/**
	 * Empty sections are declared ahead of the groups that will fill them, so
	 * the screens must not render a heading with nothing under it.
	 */
	public function test_populated_omits_sections_with_no_groups(): void {
		$populated = ToolSection::populated();

		$this->assertNotSame( [], $populated );

		foreach ( $populated as $section ) {
			$this->assertNotSame( [], $section->groups() );
		}
	}

	public function test_populated_keeps_declaration_order(): void {
		$populated = array_map( static fn ( ToolSection $section ): string => $section->value, ToolSection::populated() );
		$declared  = array_values(
			array_filter(
				array_map( static fn ( ToolSection $section ): string => $section->value, ToolSection::cases() ),
				static fn ( string $value ): bool => [] !== ToolSection::from( $value )->groups()
			)
		);

		$this->assertSame( $declared, $populated );
	}

	/**
	 * Advanced is the bucket for groups that change how the store charges money
	 * or runs maintenance, and both screens render it collapsed and never
	 * pre-checked. Nothing routine should be hiding in there — a group that
	 * lands here has to be one a shop assistant would have no business opening.
	 */
	public function test_advanced_holds_only_store_configuration(): void {
		$this->assertSame(
			[ ToolGroup::Gateways, ToolGroup::Settings, ToolGroup::System ],
			ToolSection::Advanced->groups()
		);
	}

	/**
	 * The security property the section exists for. Nothing in Advanced may be
	 * pre-granted, which is what conservative_default() leans on when a client
	 * requests no scopes at all.
	 */
	public function test_nothing_in_advanced_is_offered_by_default(): void {
		foreach ( ToolSection::Advanced->groups() as $group ) {
			$this->assertFalse( $group->enabled_by_default(), $group->value . ' ships enabled inside Advanced.' );
			$this->assertNotContains( $group->read_scope(), ApiScope::conservative_default() );
		}
	}

	/** @return iterable<string, array{ToolSection}> */
	public static function all_sections(): iterable {
		foreach ( ToolSection::cases() as $section ) {
			yield $section->value => [ $section ];
		}
	}
}
