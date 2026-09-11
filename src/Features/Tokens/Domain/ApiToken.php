<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * A persisted API token. Never holds the plain secret — only metadata.
 */
final readonly class ApiToken {

	public function __construct(
		public int $id,
		public TokenId $token_id,
		public string $label,
		public GrantedScopeSet $scopes,
		public TokenStatus $status,
		public int $owner_user_id,
		public \DateTimeImmutable $created_at,
		public ?\DateTimeImmutable $last_used_at,
		public ?\DateTimeImmutable $expires_at,
		public ?string $client_id = null,
		public ?string $audience = null,
		public ?\DateTimeImmutable $refresh_expires_at = null,
	) {}

	/** The access token has lapsed; with a refresh token outstanding the connection itself lives on. */
	public function is_access_expired( \DateTimeImmutable $now ): bool {
		return null !== $this->expires_at && $this->expires_at <= $now;
	}

	/** The connection is over: nothing, refresh token included, can revive it. */
	public function is_expired( \DateTimeImmutable $now ): bool {
		$end = $this->refresh_expires_at ?? $this->expires_at;

		return null !== $end && $end <= $now;
	}
}
