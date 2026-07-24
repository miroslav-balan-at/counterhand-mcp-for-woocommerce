<?php
/**
 * Plugin Name:       AgentGate MCP for WooCommerce
 * Plugin URI:        https://github.com/miroslavbalan/agentgate-mcp-for-woocommerce
 * Description:       Turn your WooCommerce store into a secure MCP server so AI assistants like Claude, ChatGPT and Cursor can query and manage products, orders, customers and reports — guarded by scoped, revocable API tokens.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Miroslav Balan
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       agentgate-mcp-for-woocommerce
 * Domain Path:       /languages
 *
 * WC requires at least: 8.0
 * WC tested up to:      10.9
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'AGMCP_VERSION', '0.1.0' );
define( 'AGMCP_PLUGIN_FILE', __FILE__ );
define( 'AGMCP_PLUGIN_DIR', __DIR__ );

require_once __DIR__ . '/src/Autoloader.php';
AgentGateMcp\Autoloader::register();

// HPOS (custom order tables) compatibility.
add_action( 'before_woocommerce_init', static function (): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

register_activation_hook( __FILE__, [ AgentGateMcp\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ AgentGateMcp\Plugin::class, 'deactivate' ] );

add_action( 'plugins_loaded', static function (): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'AgentGate MCP for WooCommerce requires WooCommerce to be installed and active.', 'agentgate-mcp-for-woocommerce' );
			echo '</p></div>';
		} );
		return;
	}

	AgentGateMcp\Plugin::boot();
} );
