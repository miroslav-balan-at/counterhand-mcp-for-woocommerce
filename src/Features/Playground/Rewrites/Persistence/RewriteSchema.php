<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Rewrites\Persistence;

defined( 'ABSPATH' ) || exit;

final class RewriteSchema {

	private const VERSION        = '1';
	private const VERSION_OPTION = 'counterhand_rewrites_schema_version';

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'counterhand_rewrites';
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			job_key CHAR(32) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			field VARCHAR(32) NOT NULL,
			instruction TEXT NOT NULL,
			status VARCHAR(20) NOT NULL,
			product_name TEXT NOT NULL,
			original LONGTEXT NOT NULL,
			proposal LONGTEXT NOT NULL,
			message TEXT NOT NULL,
			change_key CHAR(32) NULL,
			input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
			output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY owner (user_id, id),
			KEY job_key (job_key),
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
