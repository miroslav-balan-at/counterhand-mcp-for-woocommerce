<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth;

use Counterhand\Features\OAuth\Domain\AuthorizationGrant;
use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
use Counterhand\Features\Tokens\Domain\IssuedTokens;
use Counterhand\Features\Tokens\Domain\PlainRefreshToken;
use Counterhand\Features\Tokens\Domain\StoredToken;
use Counterhand\Features\Tokens\Domain\TokenRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * OAuth 2.1 token endpoint: exchanges a PKCE-bound single-use code for an
 * access token, and rotates refresh tokens for clients that declared the grant.
 * The access token IS a Counterhand token — same verification, scoping, rate
 * limiting, action log and revocation as before.
 */
final readonly class TokenEndpoint {

	/** A client that cannot refresh keeps a token long enough to be useful. */
	private const STANDALONE_ACCESS_TOKEN_LIFETIME_DAYS = 30;

	/** A client that can refresh gets one short enough that a leak barely matters. */
	private const REFRESHABLE_ACCESS_TOKEN_LIFETIME_SECONDS = HOUR_IN_SECONDS;

	/** Absolute, not sliding: after this the administrator approves again. */
	private const CONNECTION_LIFETIME_DAYS = 30;

	/**
	 * How long the just-retired refresh token still answers.
	 *
	 * Clients refresh proactively and reactively at the same time, so two
	 * requests in flight legitimately carry the same token; without this the
	 * loser of that race would have the connection revoked under it.
	 */
	private const ROTATION_GRACE_SECONDS = 60;

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
		return match ( (string) $request->get_param( 'grant_type' ) ) {
			'authorization_code' => $this->exchange_code( $request ),
			'refresh_token'      => $this->refresh( $request ),
			default              => $this->error( 'unsupported_grant_type', 'Only authorization_code and refresh_token are supported.' ),
		};
	}

	private function exchange_code( \WP_REST_Request $request ): \WP_REST_Response {
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

		$now    = self::now();
		$issued = $this->repository->create(
			sprintf( 'OAuth: %s', (string) wp_parse_url( $client_id, PHP_URL_HOST ) ),
			GrantedScopeSet::from_values( $grant->scopes ),
			$grant->user_id,
			$this->access_expiry( $grant, $now ),
			$grant->client_id,
			$grant->resource,
			$grant->issues_refresh_token ? $now->modify( '+' . self::CONNECTION_LIFETIME_DAYS . ' days' ) : null
		);

		return $this->tokens( $issued, $grant->scopes, $now );
	}

	private function refresh( \WP_REST_Request $request ): \WP_REST_Response {
		$parsed = PlainRefreshToken::parse( (string) $request->get_param( 'refresh_token' ) );
		if ( null === $parsed ) {
			return $this->error( 'invalid_grant', 'Refresh token is invalid.' );
		}

		[ $token_id, $secret ] = $parsed;

		$stored = $this->repository->find_active_by_token_id( $token_id );
		if ( null === $stored || null === $stored->refresh_secret_hash || $stored->token->client_id !== (string) $request->get_param( 'client_id' ) ) {
			return $this->error( 'invalid_grant', 'Refresh token is invalid.' );
		}

		$now = self::now();
		if ( $stored->token->is_expired( $now ) ) {
			$this->repository->mark_expired( $stored->token->id );

			return $this->error( 'invalid_grant', 'The connection has expired; connect the app again.' );
		}

		if ( ! $this->owner_still_capable( $stored ) ) {
			return $this->error( 'invalid_grant', 'The administrator who approved this connection can no longer manage the store.' );
		}

		$presented_hash = $secret->hash();

		// The token we just retired. Inside the grace window this is the other
		// half of a client's own race and must not cost it the connection;
		// outside it, a replay is what it looks like (OAuth 2.1 §4.3.1).
		if ( null !== $stored->previous_refresh_secret_hash && hash_equals( $stored->previous_refresh_secret_hash, $presented_hash ) ) {
			if ( ! $stored->is_within_rotation_grace( $now, self::ROTATION_GRACE_SECONDS ) ) {
				$this->repository->revoke( $stored->token->id );

				return $this->error( 'invalid_grant', 'Refresh token was reused; the connection has been revoked.' );
			}

			return $this->error( 'invalid_grant', 'This refresh token has just been replaced; use the newest one.' );
		}

		if ( ! hash_equals( $stored->refresh_secret_hash, $presented_hash ) ) {
			return $this->error( 'invalid_grant', 'Refresh token is invalid.' );
		}

		$requested = GrantedScopeSet::from_csv( str_replace( ' ', ',', (string) $request->get_param( 'scope' ) ) );
		foreach ( $requested->all() as $scope ) {
			if ( ! $stored->token->scopes->contains( $scope ) ) {
				return $this->error( 'invalid_scope', 'A refreshed token cannot gain scopes.' );
			}
		}

		$issued = $this->repository->rotate( $stored->token, $presented_hash, $now->modify( '+' . self::REFRESHABLE_ACCESS_TOKEN_LIFETIME_SECONDS . ' seconds' ) );

		// Another request rotated between the read and the write — its response
		// carries the live pair, so this one is a no-op rather than a failure.
		if ( null === $issued ) {
			return $this->error( 'invalid_grant', 'This refresh token has just been replaced; use the newest one.' );
		}

		return $this->tokens( $issued, array_map( static fn ( $scope ): string => $scope->value, $stored->token->scopes->all() ), $now );
	}

	private function access_expiry( AuthorizationGrant $grant, \DateTimeImmutable $now ): \DateTimeImmutable {
		return $grant->issues_refresh_token
			? $now->modify( '+' . self::REFRESHABLE_ACCESS_TOKEN_LIFETIME_SECONDS . ' seconds' )
			: $now->modify( '+' . self::STANDALONE_ACCESS_TOKEN_LIFETIME_DAYS . ' days' );
	}

	/** The same rule the authenticator applies on every call, one refresh earlier. */
	private function owner_still_capable( StoredToken $stored ): bool {
		$owner = get_user_by( 'id', $stored->token->owner_user_id );

		return false !== $owner && user_can( $owner, 'manage_woocommerce' );
	}

	/** @param list<string> $scopes */
	private function tokens( IssuedTokens $issued, array $scopes, \DateTimeImmutable $now ): \WP_REST_Response {
		$body = [
			'access_token' => $issued->access->to_string(),
			'token_type'   => 'Bearer',
			'expires_in'   => $issued->expires_in( $now ),
			'scope'        => implode( ' ', $scopes ),
		];

		if ( null !== $issued->refresh ) {
			$body['refresh_token'] = $issued->refresh->to_string();
		}

		$response = new \WP_REST_Response( $body, 200 );
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

	private static function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
	}
}
