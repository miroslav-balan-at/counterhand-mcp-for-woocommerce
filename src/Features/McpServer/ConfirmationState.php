<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;

defined( 'ABSPATH' ) || exit;

/**
 * The requestState of a confirmation form, which the client carries between the question
 * and the retry and must not be able to alter: it binds the connection, the tool and the
 * exact arguments, so an approval cannot be moved to another call, and it expires.
 *
 * Single use is enforced here as well, because a replayed approval of a refund would
 * refund twice. The insert is the atomic part: the option name is unique in the database.
 */
final readonly class ConfirmationState {

	public const PURGE_HOOK = 'counterhand_purge_confirmations';

	private const TTL_SECONDS = 600;

	private const USED_PREFIX = 'counterhand_confirmed_';

	/** @param array<string, mixed> $arguments */
	public function issue( AuthenticatedAgent $agent, string $tool_name, array $arguments ): string {
		$payload = (string) wp_json_encode(
			[
				'token'   => $agent->token->id,
				'user'    => $agent->token->owner_user_id,
				'tool'    => $tool_name,
				'digest'  => self::digest( $arguments ),
				'expires' => time() + self::TTL_SECONDS,
				'nonce'   => bin2hex( random_bytes( 16 ) ),
			]
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- an opaque token, not obfuscated code.
		return base64_encode( $payload ) . '.' . self::signature( $payload );
	}

	/**
	 * The nonce of a state that verifies for this exact call, or null.
	 *
	 * @param array<string, mixed> $arguments
	 */
	public function verified_nonce( string $state, AuthenticatedAgent $agent, string $tool_name, array $arguments ): ?string {
		[ $encoded, $signature ] = array_pad( explode( '.', $state, 2 ), 2, '' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- as above.
		$payload = (string) base64_decode( $encoded, true );

		if ( '' === $payload || ! hash_equals( self::signature( $payload ), $signature ) ) {
			return null;
		}

		$claims = json_decode( $payload, true );

		if ( ! is_array( $claims )
			|| ( $claims['token'] ?? null ) !== $agent->token->id
			|| ( $claims['user'] ?? null ) !== $agent->token->owner_user_id
			|| ( $claims['tool'] ?? null ) !== $tool_name
			|| self::digest( $arguments ) !== ( $claims['digest'] ?? null )
			|| time() > (int) ( $claims['expires'] ?? 0 )
		) {
			return null;
		}

		return (string) ( $claims['nonce'] ?? '' );
	}

	/** False if this approval was already spent. */
	public function consume( string $nonce ): bool {
		global $wpdb;

		// add_option() upserts, so two retries a second apart both reported success and both ran.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no core API inserts only when absent.
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::USED_PREFIX . $nonce, (string) ( time() + self::TTL_SECONDS ) ) );

		return 1 === $inserted;
	}

	public function purge_expired(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no core API deletes options by prefix and value.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d", $wpdb->esc_like( self::USED_PREFIX ) . '%', time() ) );
	}

	private static function signature( string $payload ): string {
		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	/** @param array<mixed> $arguments */
	private static function digest( array $arguments ): string {
		return hash( 'sha256', (string) wp_json_encode( self::sorted( $arguments ) ) );
	}

	/**
	 * @param  array<mixed> $value
	 * @return array<mixed>
	 */
	private static function sorted( array $value ): array {
		ksort( $value );

		return array_map( static fn ( mixed $item ): mixed => is_array( $item ) ? self::sorted( $item ) : $item, $value );
	}
}
