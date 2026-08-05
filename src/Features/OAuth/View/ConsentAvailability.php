<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth\View;

defined( 'ABSPATH' ) || exit;

/** Why a consent checkbox is or is not grantable. */
enum ConsentAvailability {
	case Grantable;
	case SwitchedOff;
	case NotRequested;

	/** The short tag on the row; the tooltip carries the how. */
	public function tag(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Grantable    => '',
			self::SwitchedOff  => __( 'Off for this store', 'counterhand-mcp-for-woocommerce' ),
			self::NotRequested => __( 'Not requested', 'counterhand-mcp-for-woocommerce' ),
		};
	}
}
