<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's admin screens, each a real menu entry.
 *
 * These were tabs on one page. Tabs implied the four screens were variations
 * of one thing; they are not — chatting with the store and exposing it to
 * outside apps are separate jobs, done at different times. Real menu entries
 * are linkable, bookmarkable and searchable, and the browser's back button
 * behaves.
 */
enum AdminScreen: string {

	case Chat     = 'counterhand-mcp';
	case Connect  = 'counterhand-mcp-connect';
	case Settings = 'counterhand-mcp-settings';
	case Log      = 'counterhand-mcp-log';

	public function menu_title(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Chat     => __( 'Chat', 'counterhand-mcp-for-woocommerce' ),
			self::Connect  => __( 'Connect AI apps', 'counterhand-mcp-for-woocommerce' ),
			self::Settings => __( 'Settings', 'counterhand-mcp-for-woocommerce' ),
			self::Log      => __( 'Action Log', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	public function page_title(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Chat     => __( 'Chat with your store', 'counterhand-mcp-for-woocommerce' ),
			self::Connect  => __( 'Connect AI apps', 'counterhand-mcp-for-woocommerce' ),
			self::Settings => __( 'Counterhand MCP settings', 'counterhand-mcp-for-woocommerce' ),
			self::Log      => __( 'Action Log', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	public function subtitle(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Chat     => __( 'Use AI here, inside WooCommerce, on a model you connect.', 'counterhand-mcp-for-woocommerce' ),
			self::Connect  => __( 'Let AI apps you already use work with this store, and manage what they may do.', 'counterhand-mcp-for-woocommerce' ),
			self::Settings => __( 'What this store exposes, and how much of it.', 'counterhand-mcp-for-woocommerce' ),
			self::Log      => __( 'What each connected app actually did.', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	public function url(): string {
		return admin_url( 'admin.php?page=' . $this->value ); // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	/** The chat is wide and self-contained, so it drops the standard page chrome. */
	public function is_full_bleed(): bool {
		return self::Chat === $this; // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}
}
