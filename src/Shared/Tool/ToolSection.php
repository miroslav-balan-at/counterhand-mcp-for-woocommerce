<?php

declare( strict_types=1 );

namespace Counterhand\Shared\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * How tool groups are grouped for a human: one heading on the settings tab and
 * on the OAuth consent screen, so neither has to hardcode an ordering.
 *
 * Advanced is the fail-safe bucket. It holds the groups that can change how the
 * store charges money or runs maintenance routines, and both screens render it
 * collapsed and unchecked — so a group that forgets to declare a section lands
 * somewhere cautious rather than somewhere convenient.
 */
enum ToolSection: string {
	case Catalog  = 'catalog';
	case Sales    = 'sales';
	case Insights = 'insights';
	case Content  = 'content';
	case Store    = 'store';
	case Advanced = 'advanced';

	public function label(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Catalog  => __( 'Catalog', 'counterhand-mcp-for-woocommerce' ),
			self::Sales    => __( 'Sales', 'counterhand-mcp-for-woocommerce' ),
			self::Insights => __( 'Insights', 'counterhand-mcp-for-woocommerce' ),
			self::Content  => __( 'Content', 'counterhand-mcp-for-woocommerce' ),
			self::Store    => __( 'Store setup', 'counterhand-mcp-for-woocommerce' ),
			self::Advanced => __( 'Advanced', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	public function description(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Catalog  => __( 'What you sell.', 'counterhand-mcp-for-woocommerce' ),
			self::Sales    => __( 'Who bought what.', 'counterhand-mcp-for-woocommerce' ),
			self::Insights => __( 'Totals and statistics. Read-only.', 'counterhand-mcp-for-woocommerce' ),
			self::Content  => __( 'The blog posts and pages around your store.', 'counterhand-mcp-for-woocommerce' ),
			self::Store    => __( 'Shipping, tax and reference data.', 'counterhand-mcp-for-woocommerce' ),
			self::Advanced => __( 'Store configuration and maintenance. Enable only if you know why you need it.', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	/** Advanced sections render collapsed and are never pre-checked. */
	public function is_advanced(): bool {
		return self::Advanced === $this; // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	/** @return list<ToolGroup> */
	public function groups(): array {
		return array_values(
			array_filter(
				ToolGroup::cases(),
				fn ( ToolGroup $group ): bool => $this === $group->section() // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			)
		);
	}

	/**
	 * Sections that currently hold at least one group, in declaration order.
	 *
	 * Screens iterate this rather than cases(), so a section stays invisible
	 * until a group actually lands in it.
	 *
	 * @return list<self>
	 */
	public static function populated(): array {
		return array_values(
			array_filter( self::cases(), static fn ( self $section ): bool => [] !== $section->groups() )
		);
	}
}
