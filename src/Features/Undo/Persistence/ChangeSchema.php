<?php

declare( strict_types=1 );

namespace Counterhand\Features\Undo\Persistence;

defined( 'ABSPATH' ) || exit;

final class ChangeSchema {

	private const VERSION        = '1';
	private const VERSION_OPTION = 'counterhand_changes_schema_version';

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'counterhand_changes';
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			change_key CHAR(32) NOT NULL,
			token_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			tool_name VARCHAR(64) NOT NULL,
			kind VARCHAR(20) NOT NULL,
			path TEXT NOT NULL,
			fields TEXT NOT NULL,
			before_state LONGTEXT NOT NULL,
			after_state LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			undone_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY change_key (change_key),
			KEY owner (token_id, user_id, created_at),
			KEY created_at (created_at)
		) {$charset_collate};"
		);

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) !== self::VERSION ) {
			self::install();
		}
	}
}
