<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Tokens\Persistence;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the tokens table DDL. Version-gated so upgrades re-run dbDelta once.
 */
final class Schema {

	private const VERSION        = '1';
	private const VERSION_OPTION = 'agmcp_schema_version';

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'agmcp_tokens';
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token_id VARCHAR(16) NOT NULL,
			secret_hash CHAR(64) NOT NULL,
			label VARCHAR(191) NOT NULL,
			scopes TEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			owner_user_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			last_used_at DATETIME DEFAULT NULL,
			expires_at DATETIME DEFAULT NULL,
			revoked_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_id (token_id),
			KEY owner_user_id (owner_user_id)
		) {$charset_collate};" );

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/** Re-runs dbDelta after plugin updates that bump the schema version. */
	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) !== self::VERSION ) {
			self::install();
		}
	}
}
