<?php
/**
 * Plugin Name:       Counterhand MCP for WooCommerce
 * Plugin URI:        https://github.com/miroslavbalan/counterhand-mcp-for-woocommerce
 * Description:       Turn your WooCommerce store into a secure MCP server so AI assistants like Claude, ChatGPT and Cursor can query and manage products, orders, customers and reports — guarded by scoped, revocable API tokens.
 * Version:           1.1.0
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

define( 'COUNTERHAND_VERSION', '1.1.0' );
define( 'COUNTERHAND_PLUGIN_FILE', __FILE__ );
define( 'COUNTERHAND_PLUGIN_DIR', __DIR__ );

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
					/*
					 * There is no free tier to evaluate, so the trial is the
					 * only way to see the plugin work before paying.
					 *
					 * A card is required because Freemius' own figures across
					 * their catalogue put trials-with-payment at ~70% conversion
					 * against ~19% without, and ~1.5% for no trial at all — the
					 * card is what separates someone deciding from someone
					 * browsing. 14 days is their baseline; longer removes the
					 * urgency to actually try it.
					 */
					'trial'               => [
						'days'               => 14,
						'is_require_payment' => true,
					],
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

// Uninstall cleanup rides Freemius' hook: the SDK owns WordPress' uninstall
// event (it records the uninstall reason), and their deployment pipeline
// rejects a zip carrying a root uninstall.php for exactly that reason.
if ( function_exists( 'counterhand_freemius' ) ) {
	counterhand_freemius()->add_action( 'after_uninstall', [ Counterhand\Uninstall::class, 'run' ] );
}

// Not on wordpress.org, so no language packs — the bundled .mo files load here or not at all.
add_action(
	'init',
	static function (): void {
		load_plugin_textdomain( 'counterhand-mcp-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
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
