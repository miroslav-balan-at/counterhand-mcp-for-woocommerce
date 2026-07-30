<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth;

use Counterhand\Features\OAuth\Domain\AuthorizationRequest;
use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
use Counterhand\Shared\CanonicalUri;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the OAuth 2.1 authorize parameters off the current request and
 * validates them (response_type, PKCE S256, RFC 8707 resource). Anything
 * short of a fully valid request yields null — the endpoint renders one
 * generic "incomplete request" page rather than leaking which check failed.
 */
final readonly class AuthorizationRequestParser {

	public function parse(): ?AuthorizationRequest {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth authorize params, not a WP form; CSRF handled by state + POST nonce on decision.
		$client_id      = esc_url_raw( wp_unslash( $_REQUEST['client_id'] ?? '' ) );
		$redirect_uri   = esc_url_raw( wp_unslash( $_REQUEST['redirect_uri'] ?? '' ) );
		$response_type  = sanitize_text_field( wp_unslash( $_REQUEST['response_type'] ?? '' ) );
		$code_challenge = sanitize_text_field( wp_unslash( $_REQUEST['code_challenge'] ?? '' ) );
		$method         = sanitize_text_field( wp_unslash( $_REQUEST['code_challenge_method'] ?? '' ) );
		$state          = sanitize_text_field( wp_unslash( $_REQUEST['state'] ?? '' ) );
		$resource       = esc_url_raw( wp_unslash( $_REQUEST['resource'] ?? '' ) );
		$scope          = sanitize_text_field( wp_unslash( $_REQUEST['scope'] ?? '' ) );
		// phpcs:enable

		if ( 'code' !== $response_type || 'S256' !== $method || '' === $client_id || '' === $redirect_uri ) {
			return null;
		}

		if ( ! Pkce::is_valid_challenge( $code_challenge ) ) {
			return null;
		}

		// RFC 8707: the resource must be our canonical MCP URI.
		if ( '' === $resource || ! CanonicalUri::matches( $resource, CanonicalUri::mcp() ) ) {
			return null;
		}

		$requested = GrantedScopeSet::from_csv( str_replace( ' ', ',', $scope ) );

		return new AuthorizationRequest(
			client_id: $client_id,
			redirect_uri: $redirect_uri,
			code_challenge: $code_challenge,
			state: $state,
			resource: CanonicalUri::mcp(),
			scopes: array_map( static fn ( ApiScope $scope ): string => $scope->value, $requested->all() ),
		);
	}
}
