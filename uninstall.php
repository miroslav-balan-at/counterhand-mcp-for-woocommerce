<?php
/**
 * Uninstall cleanup: drops plugin tables, options and transients.
 * Runs only through WordPress's uninstall mechanism.
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Plugin-owned tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ctrh_tokens" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ctrh_log" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Options.
delete_option( 'ctrh_settings' );
delete_option( 'ctrh_schema_version' );
delete_option( 'ctrh_log_schema_version' );

// Transients (rate-limit windows + display-once tokens).
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_ctrh\_%'
	    OR option_name LIKE '\_transient\_timeout\_ctrh\_%'"
);

// Scheduled events.
wp_clear_scheduled_hook( 'ctrh_purge_log' );
