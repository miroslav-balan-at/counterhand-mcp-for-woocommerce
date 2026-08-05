<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Shared\CanonicalUri;

defined( 'ABSPATH' ) || exit;

/**
 * Answers "can an AI app actually connect to this store?" before the admin
 * finds out the hard way.
 *
 * Three separate things have to hold, and each fails differently:
 *
 *  1. The endpoint answers an unauthenticated request with a 401 carrying the
 *     OAuth discovery challenge. Broken permalinks or a disabled connector show
 *     up here.
 *  2. The .well-known discovery document is actually served. Plenty of nginx
 *     configurations quietly refuse dot-prefixed paths, and until now that
 *     failed silently — the endpoint check alone passes.
 *  3. The store is reachable from the public internet over HTTPS. Cloud apps
 *     connect from the vendor's servers, so a localhost or private-network
 *     store can never work with them however correct the rest is.
 */
final readonly class ConnectReadiness {

	/** Hosts that are always the developer's own machine. */
	private const LOOPBACK_HOSTS = [ 'localhost', '127.0.0.1', '::1' ];

	/** Suffixes reserved for local development and never resolvable publicly. */
	private const LOCAL_SUFFIXES = [ '.local', '.test', '.localhost', '.invalid', '.example' ];

	public function check(): ReadinessReport {
		// Reachability first: it changes how the HTTP checks are made (TLS).
		$reachable = self::public_reachability_problem( home_url() );

		$endpoint = $this->check_endpoint( $reachable );
		if ( null !== $endpoint ) {
			return new ReadinessReport( ReadinessStatus::Error, $endpoint );
		}

		$discovery = $this->check_discovery( $reachable );
		if ( null !== $discovery ) {
			return new ReadinessReport( ReadinessStatus::Error, $discovery );
		}

		if ( null !== $reachable ) {
			return new ReadinessReport(
				ReadinessStatus::Local,
				__( 'Local site — only tools on your own machine can connect', 'counterhand-mcp-for-woocommerce' ),
				$reachable
			);
		}

		return new ReadinessReport(
			ReadinessStatus::Ok,
			__( 'Ready for every AI app', 'counterhand-mcp-for-woocommerce' ),
			__( 'The endpoint is live and advertising OAuth discovery.', 'counterhand-mcp-for-woocommerce' )
		);
	}

	/**
	 * TLS is not verified on a local site's loopback self-checks: dev certs are
	 * routinely unverifiable and would mask the real "this store is local"
	 * answer. Public sites verify; counterhand_verify_sslverify overrides both.
	 *
	 * @return array{timeout: int, sslverify: bool}
	 */
	private function http_args( ?string $local_problem ): array {
		return [
			'timeout'   => 15,
			'sslverify' => (bool) apply_filters( 'counterhand_verify_sslverify', null === $local_problem ),
		];
	}

	/** @return string|null Problem description, or null when the endpoint is fine. */
	private function check_endpoint( ?string $local_problem ): ?string {
		$response = wp_remote_post(
			CanonicalUri::mcp(),
			[
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => (string) wp_json_encode(
					[
						'jsonrpc' => '2.0',
						'id'      => 1,
						'method'  => 'initialize',
						'params'  => new \stdClass(),
					]
				),
			] + $this->http_args( $local_problem )
		);

		if ( is_wp_error( $response ) ) {
			return sprintf(
				/* translators: %s: error message */
				__( 'The endpoint could not be reached: %s', 'counterhand-mcp-for-woocommerce' ),
				$response->get_error_message()
			);
		}

		$status    = (int) wp_remote_retrieve_response_code( $response );
		$challenge = (string) wp_remote_retrieve_header( $response, 'www-authenticate' );

		if ( 401 === $status && str_contains( $challenge, 'resource_metadata' ) ) {
			return null;
		}

		return sprintf(
			/* translators: %d: HTTP status code */
			__( 'The endpoint answered with status %d instead of an OAuth challenge. Check that the connector is enabled and that permalinks work.', 'counterhand-mcp-for-woocommerce' ),
			$status
		);
	}

	/** @return string|null Problem description, or null when discovery is served. */
	private function check_discovery( ?string $local_problem ): ?string {
		$url = home_url( '/.well-known/oauth-protected-resource' );

		$response = wp_remote_get( $url, $this->http_args( $local_problem ) );

		if ( is_wp_error( $response ) ) {
			return sprintf(
				/* translators: %s: error message */
				__( 'The discovery document could not be fetched: %s', 'counterhand-mcp-for-woocommerce' ),
				$response->get_error_message()
			);
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || ! is_array( $payload ) ) {
			return sprintf(
				/* translators: %s: discovery URL */
				__( 'The server does not serve %s. Some hosts block paths starting with a dot — allow that path in your server configuration.', 'counterhand-mcp-for-woocommerce' ),
				$url
			);
		}

		if ( CanonicalUri::mcp() !== ( $payload['resource'] ?? '' ) ) {
			return __( 'The discovery document points at a different address than this store. Check for a stale cache or a URL rewrite.', 'counterhand-mcp-for-woocommerce' );
		}

		return null;
	}

	/**
	 * Why a cloud app could not reach this URL, or null when it could.
	 *
	 * Purely a look at the address: no request is made, since the point is to
	 * tell the admin *before* they spend a round trip on the vendor's site.
	 */
	public static function public_reachability_problem( string $site_url ): ?string {
		$parts  = wp_parse_url( $site_url );
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );

		if ( '' === $host ) {
			return __( 'The site address could not be read.', 'counterhand-mcp-for-woocommerce' );
		}

		if ( 'https' !== $scheme ) {
			return __( 'The store is served over HTTP. Cloud assistants require HTTPS.', 'counterhand-mcp-for-woocommerce' );
		}

		if ( in_array( $host, self::LOOPBACK_HOSTS, true ) ) {
			return __( 'The store address points at this machine, which no outside service can reach.', 'counterhand-mcp-for-woocommerce' );
		}

		foreach ( self::LOCAL_SUFFIXES as $suffix ) {
			if ( str_ends_with( $host, $suffix ) ) {
				return sprintf(
					/* translators: %s: hostname suffix, e.g. .test */
					__( 'The %s suffix is reserved for local development and does not resolve on the internet.', 'counterhand-mcp-for-woocommerce' ),
					$suffix
				);
			}
		}

		/*
		 * A bare IP address in the site URL is only reachable if it is routable.
		 * PHP's reserved-range flag does not cover carrier-grade NAT
		 * (100.64.0.0/10), so a store published on such an address is reported
		 * as reachable here and fails later at the assistant. Vanishingly rare
		 * for a site URL, and not worth hand-rolling a range table for.
		 */
		if ( filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return __( 'The store address is a private or reserved IP address, which is not routable from the internet.', 'counterhand-mcp-for-woocommerce' );
		}

		if ( ! str_contains( $host, '.' ) ) {
			return __( 'The store hostname has no public domain suffix, so it cannot be resolved from outside your network.', 'counterhand-mcp-for-woocommerce' );
		}

		return null;
	}
}
