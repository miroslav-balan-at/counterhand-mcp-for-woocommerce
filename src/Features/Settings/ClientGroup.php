<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Where an AI app connects *from*, which is the only thing that changes.
 *
 * Every client uses the same endpoint and the same CIMD consent flow, so these
 * are labels on one list rather than separate screens. The distinction earns
 * its place for exactly one reason: cloud apps reach the store from the
 * vendor's servers, so they cannot see a store that is not on the public
 * internet, while tools on the admin's own machine work against localhost
 * happily.
 *
 * Claude Desktop belongs to Cloud despite being an installed app: its custom
 * connectors are account-level and Anthropic's servers make the request.
 */
enum ClientGroup: string {

	case Cloud = 'cloud';
	case Local = 'local';

	public function label(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Cloud => __( 'Apps that connect from the cloud', 'counterhand-mcp-for-woocommerce' ),
			self::Local => __( 'Tools on your own machine', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	public function hint(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Cloud => __( 'Web, mobile and desktop assistants. These need your store to be reachable from the internet.', 'counterhand-mcp-for-woocommerce' ),
			self::Local => __( 'Terminal tools and code editors. These connect from your computer, so a local store works too.', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	/** Whether this group needs the store to be reachable from the internet. */
	public function needs_public_store(): bool {
		return self::Cloud === $this; // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}
}
