<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth;

defined( 'ABSPATH' ) || exit;

/**
 * A validated Client ID Metadata Document (CIMD): the client_id IS the
 * HTTPS URL this document was fetched from.
 */
final readonly class ClientMetadata {

	/** RFC 7591 §2: what a document that names no grant_types is taken to mean. */
	public const DEFAULT_GRANT_TYPES = [ 'authorization_code' ];

	/**
	 * @param list<string> $redirect_uris
	 * @param list<string> $grant_types
	 */
	public function __construct(
		public string $client_id,
		public string $client_name,
		public array $redirect_uris,
		public array $grant_types = self::DEFAULT_GRANT_TYPES,
	) {}

	/**
	 * Whether the client says it can refresh. Only such a client gets a
	 * short-lived access token; the rest would simply stop working when it lapsed.
	 */
	public function issues_refresh_tokens(): bool {
		return in_array( 'refresh_token', $this->grant_types, true );
	}

	public function allows_redirect_uri( string $redirect_uri ): bool {
		if ( in_array( $redirect_uri, $this->redirect_uris, true ) ) {
			return true;
		}

		// RFC 8252 §7.3: loopback redirects match port-insensitively.
		$parsed = wp_parse_url( $redirect_uri );
		if ( ! is_array( $parsed ) || ! in_array( $parsed['host'] ?? '', [ '127.0.0.1', 'localhost', '[::1]' ], true ) ) {
			return false;
		}

		foreach ( $this->redirect_uris as $registered_uri ) {
			$registered = wp_parse_url( $registered_uri );
			if ( ! is_array( $registered ) ) {
				continue;
			}

			$hosts_match = ( $registered['host'] ?? '' ) === ( $parsed['host'] ?? '' );
			$rest_match  = ( $registered['scheme'] ?? '' ) === ( $parsed['scheme'] ?? '' )
				&& ( $registered['path'] ?? '/' ) === ( $parsed['path'] ?? '/' );

			if ( $hosts_match && $rest_match ) {
				return true;
			}
		}

		return false;
	}
}
