<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Settings;

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

	case Chat     = 'agentgate-mcp';
	case Connect  = 'agentgate-mcp-connect';
	case Settings = 'agentgate-mcp-settings';
	case Log      = 'agentgate-mcp-log';

	public function menu_title(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Chat     => __( 'Chat', 'agentgate-mcp-for-woocommerce' ),
			self::Connect  => __( 'Connect AI apps', 'agentgate-mcp-for-woocommerce' ),
			self::Settings => __( 'Settings', 'agentgate-mcp-for-woocommerce' ),
			self::Log      => __( 'Action Log', 'agentgate-mcp-for-woocommerce' ),
		};
	}

	public function page_title(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Chat     => __( 'Chat with your store', 'agentgate-mcp-for-woocommerce' ),
			self::Connect  => __( 'Connect AI apps', 'agentgate-mcp-for-woocommerce' ),
			self::Settings => __( 'AgentGate MCP settings', 'agentgate-mcp-for-woocommerce' ),
			self::Log      => __( 'Action Log', 'agentgate-mcp-for-woocommerce' ),
		};
	}

	public function subtitle(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Chat     => __( 'Use AI here, inside WooCommerce, on a model you connect.', 'agentgate-mcp-for-woocommerce' ),
			self::Connect  => __( 'Let AI apps you already use work with this store, and manage what they may do.', 'agentgate-mcp-for-woocommerce' ),
			self::Settings => __( 'What this store exposes, and how much of it.', 'agentgate-mcp-for-woocommerce' ),
			self::Log      => __( 'What each connected app actually did.', 'agentgate-mcp-for-woocommerce' ),
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
