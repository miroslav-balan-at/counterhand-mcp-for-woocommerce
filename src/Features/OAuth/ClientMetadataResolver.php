<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OAuth;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves a CIMD client_id URL into validated client metadata.
 *
 * Hardening (SSRF / open-redirect):
 * - client_id must be HTTPS, without credentials, fragment or dot-segments
 * - the fetched document's client_id must equal the URL it was fetched from
 * - redirect URIs must be HTTPS or loopback-HTTP
 */
final readonly class ClientMetadataResolver {

	private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	public function resolve( string $client_id_url ): ?ClientMetadata {
		if ( ! $this->is_acceptable_client_id_url( $client_id_url ) ) {
			return null;
		}

		$cache_key = 'agmcp_cimd_' . md5( $client_id_url );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return new ClientMetadata( $client_id_url, $cached['client_name'], $cached['redirect_uris'] );
		}

		$response = wp_remote_get(
			$client_id_url,
			[
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => [ 'Accept' => 'application/json' ],
				// Defaults to true (secure). A filter allows self-signed certs in
				// local development only — never relax this in production.
				'sslverify'   => apply_filters( 'agmcp_cimd_sslverify', true, $client_id_url ),
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$document = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $document ) ) {
			return null;
		}

		// The document must claim the exact URL it lives at.
		if ( ( $document['client_id'] ?? null ) !== $client_id_url ) {
			return null;
		}

		$redirect_uris = array_values(
			array_filter(
				is_array( $document['redirect_uris'] ?? null ) ? $document['redirect_uris'] : [],
				[ $this, 'is_acceptable_redirect_uri' ]
			)
		);

		if ( [] === $redirect_uris ) {
			return null;
		}

		$client_name = sanitize_text_field( (string) ( $document['client_name'] ?? wp_parse_url( $client_id_url, PHP_URL_HOST ) ) );

		set_transient(
			$cache_key,
			[
				'client_name'   => $client_name,
				'redirect_uris' => $redirect_uris,
			],
			self::CACHE_TTL
		);

		return new ClientMetadata( $client_id_url, $client_name, $redirect_uris );
	}

	private function is_acceptable_client_id_url( string $url ): bool {
		$parsed = wp_parse_url( $url );

		return is_array( $parsed )
			&& 'https' === ( $parsed['scheme'] ?? '' )
			&& '' !== ( $parsed['host'] ?? '' )
			&& ! isset( $parsed['user'], $parsed['pass'] )
			&& ! isset( $parsed['fragment'] )
			&& ! str_contains( $parsed['path'] ?? '', '..' );
	}

	public function is_acceptable_redirect_uri( mixed $uri ): bool {
		if ( ! is_string( $uri ) ) {
			return false;
		}

		$parsed = wp_parse_url( $uri );
		if ( ! is_array( $parsed ) || isset( $parsed['user'], $parsed['pass'] ) ) {
			return false;
		}

		$scheme = $parsed['scheme'] ?? '';
		$host   = $parsed['host'] ?? '';

		if ( 'https' === $scheme ) {
			return true;
		}

		// Plain HTTP only for RFC 8252 loopback clients.
		return 'http' === $scheme && in_array( $host, [ '127.0.0.1', 'localhost', '[::1]' ], true );
	}
}
