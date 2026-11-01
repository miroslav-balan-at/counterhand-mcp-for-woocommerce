<?php

declare( strict_types=1 );

namespace Counterhand;

defined( 'ABSPATH' ) || exit;

/** Uninstall cleanup: drops the plugin's tables and options. Invoked from the root uninstall.php. */
final class Uninstall {

	/** Every site of a network: each has its own tables and options. */
	public static function run(): void {
		if ( ! is_multisite() ) {
			self::clean_site();

			return;
		}

		foreach ( get_sites(
			[
				'fields' => 'ids',
				'number' => 0,
			]
		) as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::clean_site();
			restore_current_blog();
		}
	}

	private static function clean_site(): void {
		global $wpdb;

		// Plugin-owned tables.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}counterhand_tokens" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}counterhand_changes" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- plugin-owned table on uninstall.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}counterhand_action_log" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.

		delete_option( 'counterhand_settings' );
		delete_option( 'counterhand_chat' );
		delete_option( 'counterhand_tokens_schema_version' );
		delete_option( 'counterhand_action_log_schema_version' );
		delete_option( 'counterhand_changes_schema_version' );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'counterhand\\_confirmed\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- spent one-time approvals, which no core API lists.

		// No transient sweep: every transient this plugin sets lives an hour
		// or less, and core's daily delete_expired_transients job removes the rows.

		wp_clear_scheduled_hook( 'counterhand_purge_log' );
		wp_clear_scheduled_hook( 'counterhand_purge_changes' );
		wp_clear_scheduled_hook( 'counterhand_purge_confirmations' );
	}
}
