<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

enum TokenStatus: string {
	case Active  = 'active';
	case Revoked = 'revoked';
	case Expired = 'expired';

	/** The backing values are storage keys; a screen shows this instead. */
	public function label(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Active  => __( 'Active', 'counterhand-mcp-for-woocommerce' ),
			self::Revoked => __( 'Revoked', 'counterhand-mcp-for-woocommerce' ),
			self::Expired => __( 'Expired', 'counterhand-mcp-for-woocommerce' ),
		};
	}
}
