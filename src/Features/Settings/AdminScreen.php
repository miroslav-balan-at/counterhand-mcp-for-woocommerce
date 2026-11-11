<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's admin screens, each a real menu entry, in menu order.
 *
 * Each screen has one job and one name, and the names are the vocabulary the
 * whole plugin uses (an area, an app, Access, Activity). The slugs are older than
 * the names and stay as they are, so bookmarks, links in weekly report emails and
 * the consent screen keep working.
 */
enum AdminScreen: string {

	case Overview = 'counterhand-mcp-dashboard';
	case Chat     = 'counterhand-mcp';
	case Rewrites = 'counterhand-mcp-rewrites';
	case Connect  = 'counterhand-mcp-connect';
	case Access   = 'counterhand-mcp-settings';
	case Activity = 'counterhand-mcp-log';

	/** Other plugins became a section of Access; its old address leads there. */
	public const OTHER_PLUGINS_SLUG = 'counterhand-mcp-integrations';

	/** The anchor of the Other plugins section on Access. */
	public const OTHER_PLUGINS_ANCHOR = 'counterhand-other-plugins';

	public function menu_title(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Overview => __( 'Overview', 'counterhand-mcp-for-woocommerce' ),
			self::Chat     => __( 'Chat', 'counterhand-mcp-for-woocommerce' ),
			self::Rewrites => __( 'Rewrites', 'counterhand-mcp-for-woocommerce' ),
			self::Connect  => __( 'Connect apps', 'counterhand-mcp-for-woocommerce' ),
			self::Access   => __( 'Access', 'counterhand-mcp-for-woocommerce' ),
			self::Activity => __( 'Activity', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	public function page_title(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Overview => __( 'Overview', 'counterhand-mcp-for-woocommerce' ),
			self::Chat     => __( 'Chat with your store', 'counterhand-mcp-for-woocommerce' ),
			self::Rewrites => __( 'Rewrite product texts', 'counterhand-mcp-for-woocommerce' ),
			self::Connect  => __( 'Connect apps', 'counterhand-mcp-for-woocommerce' ),
			self::Access   => __( 'Access', 'counterhand-mcp-for-woocommerce' ),
			self::Activity => __( 'Activity', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	public function subtitle(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Overview => __( 'What is set up, and what AI did today.', 'counterhand-mcp-for-woocommerce' ),
			self::Chat     => __( 'Ask your store in plain words. Changes wait for you.', 'counterhand-mcp-for-woocommerce' ),
			self::Rewrites => __( 'New product texts from the AI model, approved one by one.', 'counterhand-mcp-for-woocommerce' ),
			self::Connect  => __( 'Use Claude, ChatGPT or Cursor with your store.', 'counterhand-mcp-for-woocommerce' ),
			self::Access   => __( 'What AI may read and change in this store.', 'counterhand-mcp-for-woocommerce' ),
			self::Activity => __( 'Every call apps and the chat made, newest first.', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	public function url(): string {
		return admin_url( 'admin.php?page=' . $this->value ); // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	public static function other_plugins_url(): string {
		return self::Access->url() . '#' . self::OTHER_PLUGINS_ANCHOR;
	}

	/** The chat is wide and self-contained, so it drops the standard page chrome. */
	public function is_full_bleed(): bool {
		return self::Chat === $this; // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}
}
