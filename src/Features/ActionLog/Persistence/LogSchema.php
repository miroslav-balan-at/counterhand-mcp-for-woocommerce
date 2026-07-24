<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\ActionLog\Persistence;

defined( 'ABSPATH' ) || exit;

final class LogSchema {

	private const VERSION        = '1';
	private const VERSION_OPTION = 'agmcp_log_schema_version';

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'agmcp_log';
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			tool_name VARCHAR(64) NOT NULL,
			token_label VARCHAR(191) NOT NULL,
			outcome VARCHAR(20) NOT NULL,
			summary TEXT NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) {$charset_collate};" );

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) !== self::VERSION ) {
			self::install();
		}
	}
}
