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

	private const PERSONAL_FIELDS = [ 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'state', 'vat_id', 'customer_note', 'note', 'reviewer', 'review', 'username', 'display_name' ];

	public function log( string $tool_name, string $token_label, bool $is_error, array $arguments ): void {
		global $wpdb;

		// A site of a network, or an update nobody has opened the admin since, may not have it yet.
		LogSchema::maybe_upgrade();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.
			LogSchema::table_name(),
			[
				'created_at'  => current_time( 'mysql', true ),
				'tool_name'   => substr( $tool_name, 0, 64 ),
				'token_label' => substr( $token_label, 0, 191 ),
				'outcome'     => $is_error ? 'error' : 'success',
				'summary'     => $this->mask_pii( (string) wp_json_encode( self::without_personal_fields( $arguments ) ) ),
			],
			[ '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Names and addresses have no pattern to mask, so they are recognised by their field.
	 *
	 * @param  array<mixed> $arguments
	 * @return array<mixed>
	 */
	private static function without_personal_fields( array $arguments ): array {
		foreach ( $arguments as $key => $value ) {
			if ( in_array( (string) $key, self::PERSONAL_FIELDS, true ) ) {
				$arguments[ $key ] = '***';
				continue;
			}

			if ( is_array( $value ) ) {
				$arguments[ $key ] = self::without_personal_fields( $value );
			}
		}

		return $arguments;
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
