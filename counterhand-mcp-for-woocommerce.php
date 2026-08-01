<?php
/**
 * Plugin Name:       Counterhand MCP for WooCommerce
 * Plugin URI:        https://github.com/miroslavbalan/counterhand-mcp-for-woocommerce
 * Description:       Turn your WooCommerce store into a secure MCP server so AI assistants like Claude, ChatGPT and Cursor can query and manage products, orders, customers and reports — guarded by scoped, revocable API tokens.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 * Author:            Miroslav Balan
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

define( 'CTRH_VERSION', '0.2.0' );
define( 'CTRH_PLUGIN_FILE', __FILE__ );
define( 'CTRH_PLUGIN_DIR', __DIR__ );

/*
 * Freemius: licensing, checkout and the update server.
 *
 * It has to load here rather than on plugins_loaded — the SDK registers its own
 * hooks during this file and expects to be the first thing the plugin does. It
 * is also the one place a vendor library is allowed in (see src/Autoloader.php
 * on why): the SDK negotiates with every other Freemius-powered plugin on the
 * site and the newest copy serves them all, so bundling it cannot collide the
 * way an ordinary vendored library would.
 *
 * A missing SDK is survivable. The plugin degrades to UnlicensedFallback rather
 * than dying, because a licensing fault must never take a live store's MCP
 * endpoint down with it.
 */
if ( ! function_exists( 'counterhand_freemius' ) && file_exists( __DIR__ . '/freemius/start.php' ) ) {
	/**
	 * The Freemius instance for this plugin.
	 *
	 * @return \Freemius
	 */
	function counterhand_freemius() {
		global $counterhand_freemius;

		if ( ! isset( $counterhand_freemius ) ) {
			require_once __DIR__ . '/freemius/start.php';

			$counterhand_freemius = fs_dynamic_init(
				[
					'id'                  => '36351',
					'slug'                => 'counterhand-mcp-for-woocommerce',
					'premium_slug'        => 'counterhand-mcp-for-woocommerce-premium',
					'type'                => 'plugin',
					'public_key'          => 'pk_121fd111ca8b163d827efd34af66a',
					'is_premium'          => true,
					'has_premium_version' => true,
					'has_paid_plans'      => true,
					'has_addons'          => false,
					// Not listed on wordpress.org, so the .org-compliance rules
					// (no premium code in the free build) do not constrain us.
					'is_org_compliant'    => false,
					'menu'                => [
						'slug'    => 'counterhand-mcp',
						'account' => true,
						'contact' => false,
						'support' => false,
					],
				]
			);
		}

		return $counterhand_freemius;
	}

	counterhand_freemius();
	do_action( 'counterhand_freemius_loaded' );
}

require_once __DIR__ . '/src/Autoloader.php';
Counterhand\Autoloader::register();

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
