<?php

declare( strict_types=1 );

namespace AgentGateMcp\Shared;

defined( 'ABSPATH' ) || exit;

/**
 * The canonical MCP server URI (RFC 8707 resource identifier).
 * Used as the OAuth resource indicator and the token audience.
 */
final class CanonicalUri {

	/** No trailing slash, per the MCP authorization spec interoperability note. */
	public static function mcp(): string {
		return untrailingslashit( home_url( '/mcp' ) );
	}

	/** Compares two resource URIs, tolerating case in scheme/host per the spec. */
	public static function matches( string $candidate, string $expected ): bool {
		return strtolower( untrailingslashit( $candidate ) ) === strtolower( untrailingslashit( $expected ) );
	}
}
