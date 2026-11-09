<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Rewrites;

defined( 'ABSPATH' ) || exit;

enum RewriteStatus: string {

	case Queued    = 'queued';
	case Proposed  = 'proposed';
	case Failed    = 'failed';
	case Applying  = 'applying';
	case Applied   = 'applied';
	case Undoing   = 'undoing';
	case Undone    = 'undone';
	case Discarded = 'discarded';

	public function label(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Queued    => __( 'Waiting for the model', 'counterhand-mcp-for-woocommerce' ),
			self::Proposed  => __( 'Ready to review', 'counterhand-mcp-for-woocommerce' ),
			self::Failed    => __( 'Failed', 'counterhand-mcp-for-woocommerce' ),
			self::Applying  => __( 'Being applied', 'counterhand-mcp-for-woocommerce' ),
			self::Applied   => __( 'Applied', 'counterhand-mcp-for-woocommerce' ),
			self::Undoing   => __( 'Being undone', 'counterhand-mcp-for-woocommerce' ),
			self::Undone    => __( 'Undone', 'counterhand-mcp-for-woocommerce' ),
			self::Discarded => __( 'Discarded', 'counterhand-mcp-for-woocommerce' ),
		};
	}
}
