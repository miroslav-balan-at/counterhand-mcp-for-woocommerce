<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth;

use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
use Counterhand\Features\Tokens\Domain\TokenRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * OAuth 2.1 token endpoint: exchanges a PKCE-bound single-use code for an
 * access token. The access token IS an Counterhand token — same verification,
 * scoping, rate limiting, action log and revocation as manual tokens.
 */
final readonly class TokenEndpoint {

	private const ACCESS_TOKEN_LIFETIME_DAYS = 30;

	public function __construct(
		private TokenRepositoryInterface $repository,
		private AuthorizationCodeStore $code_store,
	) {}

	public function register_route(): void {
		register_rest_route(
			'counterhand/v1',
			'/oauth/token',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'handle' ],
					// OAuth token endpoints are public by design; the grant itself authenticates.
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$grant_type = (string) $request->get_param( 'grant_type' );

		if ( 'authorization_code' !== $grant_type ) {
			return $this->error( 'unsupported_grant_type', 'Only authorization_code is supported.' );
		}

		$code          = (string) $request->get_param( 'code' );
		$code_verifier = (string) $request->get_param( 'code_verifier' );
		$client_id     = (string) $request->get_param( 'client_id' );
		$redirect_uri  = (string) $request->get_param( 'redirect_uri' );

		if ( '' === $code || '' === $code_verifier || '' === $client_id ) {
			return $this->error( 'invalid_request', 'code, code_verifier and client_id are required.' );
		}

		$grant = $this->code_store->consume( $code );
		if ( null === $grant ) {
			return $this->error( 'invalid_grant', 'Authorization code is invalid, expired or already used.' );
		}

		if ( $grant->client_id !== $client_id ) {
			return $this->error( 'invalid_grant', 'client_id does not match the authorization.' );
		}

		if ( '' !== $redirect_uri && $grant->redirect_uri !== $redirect_uri ) {
			return $this->error( 'invalid_grant', 'redirect_uri does not match the authorization.' );
		}

		if ( ! Pkce::verify( $grant->code_challenge, $code_verifier ) ) {
			return $this->error( 'invalid_grant', 'PKCE verification failed.' );
		}

		$scope_set = GrantedScopeSet::from_values( $grant->scopes );

		$client_host = (string) wp_parse_url( $client_id, PHP_URL_HOST );
		$expires_at  = ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )
			->modify( '+' . self::ACCESS_TOKEN_LIFETIME_DAYS . ' days' );

		$plain_token = $this->repository->create(
			sprintf( 'OAuth: %s', $client_host ),
			$scope_set,
			$grant->user_id,
			$expires_at,
			$grant->client_id,
			$grant->resource
		);

		$response = new \WP_REST_Response(
			[
				'access_token' => $plain_token->to_string(),
				'token_type'   => 'Bearer',
				'expires_in'   => self::ACCESS_TOKEN_LIFETIME_DAYS * DAY_IN_SECONDS,
				'scope'        => implode( ' ', $grant->scopes ),
			],
			200
		);

		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	private function error( string $error_code, string $description ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'error'             => $error_code,
				'error_description' => $description,
			],
			400
		);
	}
}
