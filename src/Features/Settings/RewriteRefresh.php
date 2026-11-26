<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Rebuilds the rewrite rules once after AI access is switched on or off.
 *
 * /mcp, /mcp-authorize and the /.well-known documents are rewrite rules that only
 * exist while AI access is on. Activation writes them, but a store starts with it
 * off, so the next flush by anyone (WooCommerce's first-run one, saving permalinks,
 * another plugin) drops them, and switching access on later left /mcp at 404.
 *
 * The request that saves the setting was booted with the old value, so flushing
 * there would rebuild the old rules. The flush is queued instead and runs on
 * wp_loaded of the next request, after every init callback has added its rules,
 * as WooCommerce does with woocommerce_queue_flush_rewrite_rules.
 */
final readonly class RewriteRefresh implements FeatureInterface {

	public const QUEUE_OPTION = 'counterhand_queue_flush_rewrite_rules';

	public function register(): void {
		add_action( 'add_option_' . PluginSettings::OPTION, [ $this, 'queue_when_added' ], 10, 2 );
		add_action( 'update_option_' . PluginSettings::OPTION, [ $this, 'queue_when_switched' ], 10, 2 );
		add_action( 'wp_loaded', [ $this, 'flush_queued' ] );
	}

	public function queue_when_added( string $option, mixed $value ): void {
		$this->queue_when_switched( [], $value );
	}

	public function queue_when_switched( mixed $old_value, mixed $value ): void {
		if ( self::is_on( $old_value ) !== self::is_on( $value ) ) {
			update_option( self::QUEUE_OPTION, 'yes', true );
		}
	}

	public function flush_queued(): void {
		if ( 'yes' !== get_option( self::QUEUE_OPTION ) ) {
			return;
		}

		// Removed first, so two requests at once flush at most twice, never in a loop.
		delete_option( self::QUEUE_OPTION );
		flush_rewrite_rules( false );
	}

	private static function is_on( mixed $settings ): bool {
		return is_array( $settings ) && ! empty( $settings['enabled'] );
	}
}
