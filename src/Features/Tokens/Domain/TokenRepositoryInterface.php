<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

interface TokenRepositoryInterface {

	/** A refresh token is issued exactly when $refresh_expires_at is given. */
	public function create(
		string $label,
		GrantedScopeSet $scopes,
		int $owner_user_id,
		?\DateTimeImmutable $expires_at,
		?string $client_id = null,
		?string $audience = null,
		?\DateTimeImmutable $refresh_expires_at = null
	): IssuedTokens;

	/**
	 * Replaces both secrets; the outgoing refresh hash is kept one step so a
	 * replay of it can be recognised as such. Null when the row no longer holds
	 * $expected_refresh_hash — another request rotated it first.
	 */
	public function rotate( ApiToken $token, string $expected_refresh_hash, \DateTimeImmutable $access_expires_at ): ?IssuedTokens;

	public function find_active_by_token_id( TokenId $token_id ): ?StoredToken;

	/** @return list<ApiToken> */
	public function list_all(): array;

	public function revoke( int $id ): bool;

	public function mark_expired( int $id ): void;

	public function touch_last_used( int $id ): void;
}
