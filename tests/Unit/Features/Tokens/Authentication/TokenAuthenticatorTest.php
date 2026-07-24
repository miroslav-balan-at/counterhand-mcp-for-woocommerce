<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\Tokens\Authentication;

use AgentGateMcp\Features\Settings\PluginSettings;
use AgentGateMcp\Features\Tokens\Authentication\RateLimiter;
use AgentGateMcp\Features\Tokens\Authentication\TokenAuthenticator;
use AgentGateMcp\Features\Tokens\Domain\ApiToken;
use AgentGateMcp\Features\Tokens\Domain\GrantedScopeSet;
use AgentGateMcp\Features\Tokens\Domain\PlainToken;
use AgentGateMcp\Features\Tokens\Domain\StoredToken;
use AgentGateMcp\Features\Tokens\Domain\TokenId;
use AgentGateMcp\Features\Tokens\Domain\TokenRepositoryInterface;
use AgentGateMcp\Features\Tokens\Domain\TokenSecret;
use AgentGateMcp\Features\Tokens\Domain\TokenStatus;
use AgentGateMcp\Shared\Exception\AuthenticationFailedException;
use AgentGateMcp\Shared\Exception\RateLimitExceededException;
use AgentGateMcp\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class TokenAuthenticatorTest extends TestCase {

	private TokenId $token_id;
	private TokenSecret $secret;
	private string $bearer;

	protected function setUp(): void {
		parent::setUp();

		$this->token_id = TokenId::generate();
		$this->secret   = TokenSecret::generate();
		$this->bearer   = 'Bearer ' . PlainToken::compose( $this->token_id, $this->secret )->to_string();

		// Defaults for the happy path; individual tests override.
		Functions\when( 'get_option' )->justReturn( [ 'rate_limit_per_minute' => 60 ] );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'get_user_by' )->justReturn( (object) [ 'ID' => 846 ] );
		Functions\when( 'user_can' )->justReturn( true );
		Functions\when( 'wp_set_current_user' )->justReturn( null );
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	public function test_valid_token_authenticates_and_exposes_scopes(): void {
		$agent = $this->authenticator( $this->stored_token() )->authenticate( $this->bearer, null );

		self::assertSame( 846, $agent->token->owner_user_id );
		self::assertTrue( $agent->scopes()->contains( \AgentGateMcp\Features\Tokens\Domain\ApiScope::ProductsRead ) );
	}

	public function test_wrong_secret_fails_with_generic_message(): void {
		$wrong_bearer = 'Bearer ' . PlainToken::compose( $this->token_id, TokenSecret::generate() )->to_string();

		$this->expectException( AuthenticationFailedException::class );
		$this->expectExceptionMessage( 'Invalid or missing API token.' );

		$this->authenticator( $this->stored_token() )->authenticate( $wrong_bearer, null );
	}

	public function test_unknown_token_id_fails(): void {
		$this->expectException( AuthenticationFailedException::class );

		$this->authenticator( null )->authenticate( $this->bearer, null );
	}

	public function test_missing_header_fails(): void {
		$this->expectException( AuthenticationFailedException::class );

		$this->authenticator( $this->stored_token() )->authenticate( null, null );
	}

	public function test_expired_token_fails_and_is_marked(): void {
		$repository = $this->repository( $this->stored_token( expires_at: new \DateTimeImmutable( '-1 hour' ) ) );
		$repository->expects( self::once() )->method( 'mark_expired' );

		$this->expectException( AuthenticationFailedException::class );

		( new TokenAuthenticator( $repository, $this->rate_limiter() ) )->authenticate( $this->bearer, null );
	}

	public function test_demoted_owner_fail_closes_token(): void {
		Functions\when( 'user_can' )->justReturn( false );

		$this->expectException( AuthenticationFailedException::class );

		$this->authenticator( $this->stored_token() )->authenticate( $this->bearer, null );
	}

	public function test_deleted_owner_fail_closes_token(): void {
		Functions\when( 'get_user_by' )->justReturn( false );

		$this->expectException( AuthenticationFailedException::class );

		$this->authenticator( $this->stored_token() )->authenticate( $this->bearer, null );
	}

	public function test_over_budget_raises_rate_limit(): void {
		Functions\when( 'get_transient' )->justReturn( 60 );

		$this->expectException( RateLimitExceededException::class );

		$this->authenticator( $this->stored_token() )->authenticate( $this->bearer, null );
	}

	public function test_fallback_header_is_accepted(): void {
		$raw_token = PlainToken::compose( $this->token_id, $this->secret )->to_string();

		$agent = $this->authenticator( $this->stored_token() )->authenticate( null, $raw_token );

		self::assertSame( 846, $agent->token->owner_user_id );
	}

	private function stored_token( ?\DateTimeImmutable $expires_at = null ): StoredToken {
		return new StoredToken(
			new ApiToken(
				id: 1,
				token_id: $this->token_id,
				label: 'test',
				scopes: GrantedScopeSet::from_csv( 'products:read' ),
				status: TokenStatus::Active,
				owner_user_id: 846,
				created_at: new \DateTimeImmutable( '-1 day' ),
				last_used_at: new \DateTimeImmutable( '-1 second' ),
				expires_at: $expires_at,
			),
			$this->secret->hash()
		);
	}

	private function authenticator( ?StoredToken $stored ): TokenAuthenticator {
		return new TokenAuthenticator( $this->repository( $stored ), $this->rate_limiter() );
	}

	private function repository( ?StoredToken $stored ): TokenRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject {
		$repository = $this->createMock( TokenRepositoryInterface::class );
		$repository->method( 'find_active_by_token_id' )->willReturn( $stored );

		return $repository;
	}

	private function rate_limiter(): RateLimiter {
		return new RateLimiter( new PluginSettings() );
	}
}
