<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth;

defined( 'ABSPATH' ) || exit;

/**
 * PKCE S256 (RFC 7636). S256 is the only supported method — mandatory per
 * the MCP authorization spec.
 */
final readonly class Pkce {

	public static function is_valid_challenge( string $code_challenge ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9_-]{43,128}$/', $code_challenge );
	}

	public static function verify( string $code_challenge, string $code_verifier ): bool {
		$expected = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- RFC 7636 S256 computation.

		return hash_equals( $code_challenge, $expected );
	}
}
