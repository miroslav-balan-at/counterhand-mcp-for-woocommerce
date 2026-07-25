<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OAuth;

defined( 'ABSPATH' ) || exit;

/**
 * A validated Client ID Metadata Document (CIMD): the client_id IS the
 * HTTPS URL this document was fetched from.
 */
final readonly class ClientMetadata {

	/** @param list<string> $redirect_uris */
	public function __construct(
		public string $client_id,
		public string $client_name,
		public array $redirect_uris,
	) {}

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
