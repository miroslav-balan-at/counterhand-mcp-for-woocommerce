<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Tokens\Authentication;

use AgentGateMcp\Features\Tokens\Domain\PlainToken;
use AgentGateMcp\Features\Tokens\Domain\TokenRepositoryInterface;
use AgentGateMcp\Shared\Exception\AuthenticationFailedException;
use AgentGateMcp\Shared\Exception\RateLimitExceededException;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies a Bearer token and binds the request to the token's owner.
 *
 * Every failure raises the same generic AuthenticationFailedException —
 * callers can never distinguish bad id / bad secret / revoked / expired /
 * demoted owner (no auth oracle).
 */
final readonly class TokenAuthenticator {

	private const LAST_USED_THROTTLE_SECONDS = 60;

	public function __construct(
		private TokenRepositoryInterface $repository,
		private RateLimiter $rate_limiter,
	) {}

	/**
	 * @throws AuthenticationFailedException On any verification failure.
	 * @throws RateLimitExceededException When the token is over budget.
	 */
	public function authenticate( ?string $authorization_header, ?string $fallback_header ): AuthenticatedAgent {
		$raw_token = $this->extract_token( $authorization_header, $fallback_header );

		$parsed = PlainToken::parse( $raw_token );
		if ( null === $parsed ) {
			throw new AuthenticationFailedException();
		}

		[ $token_id, $secret ] = $parsed;

		$stored = $this->repository->find_active_by_token_id( $token_id );
		if ( null === $stored ) {
			throw new AuthenticationFailedException();
		}

		$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		if ( $stored->token->is_expired( $now ) ) {
			$this->repository->mark_expired( $stored->token->id );
			throw new AuthenticationFailedException();
		}

		if ( ! hash_equals( $stored->secret_hash, $secret->hash() ) ) {
			throw new AuthenticationFailedException();
		}

		// The scope can never exceed the owner: a deleted or demoted owner
		// fail-closes every token they created, live.
		$owner = get_user_by( 'id', $stored->token->owner_user_id );
		if ( false === $owner || ! user_can( $owner, 'manage_woocommerce' ) ) {
			throw new AuthenticationFailedException();
		}

		$this->rate_limiter->hit( $stored->token->token_id->value );

		$this->touch_last_used_throttled( $stored->token->id, $stored->token->last_used_at, $now );

		// Bind the request to the owner so WooCommerce's own REST permission
		// checks evaluate against a real capable user.
		wp_set_current_user( $stored->token->owner_user_id );

		return new AuthenticatedAgent( $stored->token );
	}

	private function extract_token( ?string $authorization_header, ?string $fallback_header ): string {
		if ( null !== $authorization_header && 1 === preg_match( '/^Bearer\s+(\S+)$/i', trim( $authorization_header ), $matches ) ) {
			return $matches[1];
		}

		// Some Apache/CGI setups strip the Authorization header entirely.
		if ( null !== $fallback_header && '' !== trim( $fallback_header ) ) {
			return trim( $fallback_header );
		}

		throw new AuthenticationFailedException();
	}

	private function touch_last_used_throttled( int $id, ?\DateTimeImmutable $last_used_at, \DateTimeImmutable $now ): void {
		$is_stale = null === $last_used_at
			|| ( $now->getTimestamp() - $last_used_at->getTimestamp() ) > self::LAST_USED_THROTTLE_SECONDS;

		if ( $is_stale ) {
			$this->repository->touch_last_used( $id );
		}
	}
}
