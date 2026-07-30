<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth;

use Counterhand\Features\OAuth\Domain\AuthorizationGrant;

defined( 'ABSPATH' ) || exit;

/**
 * Single-use, short-lived authorization codes. Transient-backed: consuming
 * a code deletes it atomically enough for the 2-minute window it lives in.
 */
final readonly class AuthorizationCodeStore {

	private const TTL_SECONDS = 120;

	public function mint( AuthorizationGrant $grant ): string {
		$code = 'agc_' . rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- encoding random bytes, not obfuscation.

		set_transient( 'ctrh_ac_' . hash( 'sha256', $code ), $grant->to_array(), self::TTL_SECONDS );

		return $code;
	}

	public function consume( string $code ): ?AuthorizationGrant {
		$transient_key = 'ctrh_ac_' . hash( 'sha256', $code );

		$grant = get_transient( $transient_key );
		delete_transient( $transient_key );

		return AuthorizationGrant::from_array( $grant );
	}
}
