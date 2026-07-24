<?php
/**
 * Uninstall cleanup: drops plugin tables, options and transients.
 * Runs only through WordPress's uninstall mechanism.
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Plugin-owned tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}agmcp_tokens" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}agmcp_log" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Options.
delete_option( 'agmcp_settings' );
delete_option( 'agmcp_schema_version' );
delete_option( 'agmcp_log_schema_version' );

// Transients (rate-limit windows + display-once tokens).
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_agmcp\_%'
	    OR option_name LIKE '\_transient\_timeout\_agmcp\_%'"
);

// Scheduled events.
wp_clear_scheduled_hook( 'agmcp_purge_log' );
