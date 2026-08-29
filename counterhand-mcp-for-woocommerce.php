<?php
/**
 * Plugin Name:       Counterhand MCP for WooCommerce
 * Plugin URI:        https://counterhand.app
 * Description:       Turn your WooCommerce store into a secure MCP server so AI assistants like Claude, ChatGPT and Cursor can query and manage products, orders, customers and reports — guarded by scoped, revocable API tokens.
 * Version:           1.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 * Author:            Miroslav Balan
 * Author URI:        https://github.com/miroslav-balan-at
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       counterhand-mcp-for-woocommerce
 * Domain Path:       /languages
 *
 * WC requires at least: 8.0
 * WC tested up to:      10.9
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'COUNTERHAND_VERSION', '1.2.0' );
define( 'COUNTERHAND_PLUGIN_FILE', __FILE__ );
define( 'COUNTERHAND_PLUGIN_DIR', __DIR__ );

require_once __DIR__ . '/src/Autoloader.php';
Counterhand\Autoloader::register();

// Language packs from wordpress.org take precedence; when none exists for the
// locale, WordPress is pointed at the bundled files instead.
add_filter(
	'lang_dir_for_domain',
	static function ( string|false $path, string $domain ): string|false {
		if ( 'counterhand-mcp-for-woocommerce' !== $domain || false !== $path ) {
			return $path;
		}

		return __DIR__ . '/languages/';
	},
	10,
	2
);

// HPOS (custom order tables) compatibility.
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

register_activation_hook( __FILE__, [ Counterhand\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Counterhand\Plugin::class, 'deactivate' ] );

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Counterhand MCP for WooCommerce requires WooCommerce to be installed and active.', 'counterhand-mcp-for-woocommerce' );
					echo '</p></div>';
				}
			);
			return;
		}

		Counterhand\Plugin::boot();
	}
);
