<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

interface TokenRepositoryInterface {

	/**
	 * A refresh token is issued exactly when $refresh_expires_at is given.
	 *
	 * @param list<string> $approved_tools The integration tools the person approved.
	 */
	public function create(
		string $label,
		GrantedScopeSet $scopes,
		int $owner_user_id,
		?\DateTimeImmutable $expires_at,
		?string $client_id = null,
		?string $audience = null,
		?\DateTimeImmutable $refresh_expires_at = null,
		array $approved_tools = []
	): IssuedTokens;

	/**
	 * Replaces both secrets; the outgoing hashes are kept one step so a replay
	 * of the refresh token can be recognised as such, and the new pair is kept
	 * sealed with $retired_refresh for a duplicate of this request
	 * (StoredToken::ROTATION_GRACE_SECONDS). Null when the row no longer holds
	 * the hash of $retired_refresh — another request rotated it first.
	 */
	public function rotate( ApiToken $token, TokenSecret $retired_refresh, \DateTimeImmutable $access_expires_at ): ?IssuedTokens;

	/**
	 * Drops what a rotation kept for its grace window (the sealed pair and the
	 * retired access hash), so a database copy and an old refresh token
	 * together cannot open the current pair later.
	 */
	public function forget_rotation( int $id ): void;

	public function find_active_by_token_id( TokenId $token_id ): ?StoredToken;

	/** @return list<ApiToken> */
	public function list_all(): array;

	public function revoke( int $id ): bool;

	public function mark_expired( int $id ): void;

	public function touch_last_used( int $id ): void;
}
