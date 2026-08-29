<?php

declare( strict_types=1 );

namespace Counterhand\Features\ActionLog;

use Counterhand\Features\ActionLog\Persistence\LogSchema;

defined( 'ABSPATH' ) || exit;

/**
 * Writes one row per tool call. PII (emails, phone-like numbers) is masked
 * BEFORE storage — the log never contains customer contact data.
 */
final readonly class ActionLogger {

	public function log( string $tool_name, string $token_label, bool $is_error, array $arguments ): void {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.
			LogSchema::table_name(),
			[
				'created_at'  => current_time( 'mysql', true ),
				'tool_name'   => substr( $tool_name, 0, 64 ),
				'token_label' => substr( $token_label, 0, 191 ),
				'outcome'     => $is_error ? 'error' : 'success',
				'summary'     => $this->mask_pii( (string) wp_json_encode( $arguments ) ),
			],
			[ '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	public function mask_pii( string $text ): string {
		// Email addresses → j***@d***.tld
		$text = (string) preg_replace_callback(
			'/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/',
			static function ( array $matches ): string {
				[ $local, $domain ] = explode( '@', $matches[0], 2 );

				return $local[0] . '***@' . $domain[0] . '***' . strrchr( $domain, '.' );
			},
			$text
		);

		// Phone-like sequences (7+ digits, allowing separators) → keep last 2.
		return (string) preg_replace_callback(
			'/\+?\d[\d\s\/-]{6,}\d/',
			static fn ( array $matches ): string => '***' . substr( preg_replace( '/\D/', '', $matches[0] ), -2 ),
			$text
		);
	}
}
