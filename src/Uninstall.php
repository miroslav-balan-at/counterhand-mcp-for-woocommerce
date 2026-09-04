<?php

declare( strict_types=1 );

namespace Counterhand;

defined( 'ABSPATH' ) || exit;

/** Uninstall cleanup: drops the plugin's tables and options. Invoked from the root uninstall.php. */
final class Uninstall {

	public static function run(): void {
		global $wpdb;

		// Plugin-owned tables.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}counterhand_tokens" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}counterhand_action_log" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.

		delete_option( 'counterhand_settings' );
		delete_option( 'counterhand_tokens_schema_version' );
		delete_option( 'counterhand_action_log_schema_version' );

		// No transient sweep: every transient this plugin sets lives five minutes
		// or less, and core's daily delete_expired_transients job removes the rows.

		wp_clear_scheduled_hook( 'counterhand_purge_log' );
	}
}
