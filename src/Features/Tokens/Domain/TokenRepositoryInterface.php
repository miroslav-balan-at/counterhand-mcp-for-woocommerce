<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

interface TokenRepositoryInterface {

	public function create(
		string $label,
		GrantedScopeSet $scopes,
		int $owner_user_id,
		?\DateTimeImmutable $expires_at,
		?string $client_id = null,
		?string $audience = null
	): PlainToken;

	public function find_active_by_token_id( TokenId $token_id ): ?StoredToken;

	/** @return list<ApiToken> */
	public function list_all(): array;

	public function revoke( int $id ): bool;

	public function mark_expired( int $id ): void;

	public function touch_last_used( int $id ): void;
}
