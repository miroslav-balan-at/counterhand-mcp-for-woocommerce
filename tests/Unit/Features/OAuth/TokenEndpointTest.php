<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\OAuth;

use Counterhand\Features\OAuth\AuthorizationCodeStore;
use Counterhand\Features\OAuth\Domain\AuthorizationGrant;
use Counterhand\Features\OAuth\TokenEndpoint;
use Counterhand\Tests\Doubles\InMemoryTokenRepository;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The token lifecycle as a client sees it: what a code buys depending on what
 * the client declared, and what a refresh token does the second time round.
 */
final class TokenEndpointTest extends TestCase {

	private const CLIENT = 'https://app.example/client.json';

	private InMemoryTokenRepository $repository;
	private AuthorizationCodeStore $codes;
	private TokenEndpoint $endpoint;

	protected function setUp(): void {
		parent::setUp();

		$transients = [];
		Functions\when( 'set_transient' )->alias(
			static function ( string $key, mixed $value ) use ( &$transients ): bool {
				$transients[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			static function ( string $key ) use ( &$transients ): mixed {
				return $transients[ $key ] ?? false;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			static function ( string $key ) use ( &$transients ): bool {
				unset( $transients[ $key ] );
				return true;
			}
		);
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'get_user_by' )->justReturn( (object) [ 'ID' => 7 ] );
		Functions\when( 'user_can' )->justReturn( true );

		$this->repository = new InMemoryTokenRepository();
		$this->codes      = new AuthorizationCodeStore();
		$this->endpoint   = new TokenEndpoint( $this->repository, $this->codes );
	}

	public function test_a_client_that_declared_refresh_gets_a_short_token_and_a_refresh_token(): void {
		$body = $this->exchange( refreshable: true );

		self::assertStringStartsWith( 'counterhand_refresh_', $body['refresh_token'] );
		self::assertLessThanOrEqual( HOUR_IN_SECONDS, $body['expires_in'] );
	}

	public function test_a_client_that_did_not_declare_refresh_keeps_a_long_token_and_gets_none(): void {
		$body = $this->exchange( refreshable: false );

		self::assertArrayNotHasKey( 'refresh_token', $body );
		self::assertGreaterThan( DAY_IN_SECONDS, $body['expires_in'] );
	}

	public function test_refreshing_rotates_both_secrets(): void {
		$first = $this->exchange( refreshable: true );

		$second = $this->refresh( $first['refresh_token'] );

		self::assertSame( 200, $second->get_status() );
		self::assertNotSame( $first['refresh_token'], $second->get_data()['refresh_token'] );
		self::assertNotSame( $first['access_token'], $second->get_data()['access_token'] );
	}

	/**
	 * Clients refresh proactively and on a 401 at the same moment, so the same
	 * token legitimately arrives twice. The loser of that race must not cost
	 * the store its connection.
	 */
	public function test_a_second_refresh_in_the_same_moment_does_not_revoke_the_connection(): void {
		$first = $this->exchange( refreshable: true );

		$winner = $this->refresh( $first['refresh_token'] );
		$loser  = $this->refresh( $first['refresh_token'] );

		self::assertSame( 400, $loser->get_status() );
		self::assertSame( 'invalid_grant', $loser->get_data()['error'] );
		self::assertSame( 200, $this->refresh( $winner->get_data()['refresh_token'] )->get_status(), 'the connection still works' );
	}

	public function test_a_refresh_token_replayed_long_after_it_was_retired_revokes_the_connection(): void {
		$first = $this->exchange( refreshable: true );
		$this->refresh( $first['refresh_token'] );

		// Well past the grace window: this is a leak, not a race.
		$this->repository->rotated_at( 1, new \DateTimeImmutable( '-1 hour' ) );

		$replayed = $this->refresh( $first['refresh_token'] );

		self::assertSame( 'invalid_grant', $replayed->get_data()['error'] );
		self::assertSame( [], array_filter( $this->repository->list_all(), static fn ( $token ): bool => 'active' === $token->status->value ) );
	}

	public function test_a_refresh_token_is_bound_to_its_client(): void {
		$first = $this->exchange( refreshable: true );

		$response = $this->refresh( $first['refresh_token'], client_id: 'https://someone-else.example/client.json' );

		self::assertSame( 'invalid_grant', $response->get_data()['error'] );
	}

	/** @return array<string, mixed> */
	private function exchange( bool $refreshable ): array {
		$verifier  = str_repeat( 'v', 43 );
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );

		$code = $this->codes->mint(
			new AuthorizationGrant(
				client_id: self::CLIENT,
				redirect_uri: 'https://app.example/callback',
				code_challenge: $challenge,
				scopes: [ 'products:read' ],
				user_id: 7,
				resource: 'https://store.example/mcp',
				issues_refresh_token: $refreshable,
			)
		);

		$response = $this->endpoint->handle(
			$this->request(
				[
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'code_verifier' => $verifier,
					'client_id'     => self::CLIENT,
				]
			)
		);

		self::assertSame( 200, $response->get_status(), (string) json_encode( $response->get_data() ) );

		return $response->get_data();
	}

	private function refresh( string $refresh_token, string $client_id = self::CLIENT ): \WP_REST_Response {
		return $this->endpoint->handle(
			$this->request(
				[
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh_token,
					'client_id'     => $client_id,
				]
			)
		);
	}

	/** @param array<string, string> $params */
	private function request( array $params ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'POST', '/counterhand/v1/oauth/token' );
		$request->set_default_params( $params );

		return $request;
	}
}
