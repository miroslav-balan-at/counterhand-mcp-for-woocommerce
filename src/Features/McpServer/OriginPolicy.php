<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

/**
 * Which browser origins may call the MCP endpoint (Streamable HTTP: a request
 * whose Origin is present and invalid gets 403, against DNS rebinding).
 *
 * Accepted: the store's own origin, and loopback origins on any port, where
 * browser-based developer tools such as MCP Inspector run. A rebinding
 * attacker's page carries its own domain as Origin, so neither opens a hole.
 * Server-side clients send no Origin and are not affected. Anything else is
 * added with the counterhand_mcp_allowed_origins filter.
 */
final readonly class OriginPolicy {

	private const LOOPBACK_HOSTS = [ 'localhost', '127.0.0.1', '[::1]' ];

	private const DEFAULT_PORTS = [
		'http'  => 80,
		'https' => 443,
	];

	public function allows( string $origin ): bool {
		$candidate = self::normalize( $origin );

		// "null" (a sandboxed frame or a file:// page) normalizes to nothing.
		if ( null === $candidate ) {
			return false;
		}

		if ( in_array( (string) wp_parse_url( $candidate, PHP_URL_HOST ), self::LOOPBACK_HOSTS, true ) ) {
			return true;
		}

		/**
		 * Filters the browser origins, besides the store's own and loopback ones,
		 * that may call the MCP endpoint.
		 *
		 * @param list<string> $origins Origins such as "https://app.example.com".
		 */
		$extra   = (array) apply_filters( 'counterhand_mcp_allowed_origins', [] );
		$allowed = array_map( [ self::class, 'normalize' ], [ home_url(), ...$extra ] );

		return in_array( $candidate, $allowed, true );
	}

	/** scheme://host[:port], lower-cased, the default port dropped; null when it is not an http(s) origin. */
	private static function normalize( mixed $url ): ?string {
		$parts = is_string( $url ) ? wp_parse_url( trim( $url ) ) : false;
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return null;
		}

		$scheme = strtolower( $parts['scheme'] );
		if ( ! isset( self::DEFAULT_PORTS[ $scheme ] ) ) {
			return null;
		}

		$port = isset( $parts['port'] ) && self::DEFAULT_PORTS[ $scheme ] !== (int) $parts['port'] ? ':' . (int) $parts['port'] : '';

		return $scheme . '://' . strtolower( $parts['host'] ) . $port;
	}
}
