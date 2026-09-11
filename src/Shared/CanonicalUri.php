<?php

declare( strict_types=1 );

namespace Counterhand\Shared;

defined( 'ABSPATH' ) || exit;

/**
 * The canonical MCP server URI (RFC 8707 resource identifier).
 * Used as the OAuth resource indicator and the token audience.
 */
final class CanonicalUri {

	/** The one declaration of the endpoint's path — rewrites and URLs both derive from it. */
	public const MCP_PATH = 'mcp';

	/** No trailing slash, per the MCP authorization spec interoperability note. */
	public static function mcp(): string {
		return untrailingslashit( home_url( '/' . self::MCP_PATH ) );
	}

	/** The OAuth issuer (RFC 8414); the iss parameter must be byte-identical to it (RFC 9207). */
	public static function issuer(): string {
		return home_url();
	}

	/** Compares two resource URIs, tolerating case in scheme/host per the spec. */
	public static function matches( string $candidate, string $expected ): bool {
		return strtolower( untrailingslashit( $candidate ) ) === strtolower( untrailingslashit( $expected ) );
	}
}
