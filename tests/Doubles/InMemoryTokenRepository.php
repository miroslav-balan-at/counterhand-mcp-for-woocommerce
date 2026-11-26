<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Doubles;

use Counterhand\Features\Tokens\Domain\ApiToken;
use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
use Counterhand\Features\Tokens\Domain\IssuedTokens;
use Counterhand\Features\Tokens\Domain\PlainRefreshToken;
use Counterhand\Features\Tokens\Domain\PlainToken;
use Counterhand\Features\Tokens\Domain\SealedRotation;
use Counterhand\Features\Tokens\Domain\StoredToken;
use Counterhand\Features\Tokens\Domain\TokenId;
use Counterhand\Features\Tokens\Domain\TokenRepositoryInterface;
use Counterhand\Features\Tokens\Domain\TokenSecret;
use Counterhand\Features\Tokens\Domain\TokenStatus;

/**
 * The tokens table without the database, so the token endpoint's lifecycle
 * (issue, refresh, rotate, revoke) can be exercised end to end.
 */
final class InMemoryTokenRepository implements TokenRepositoryInterface {

	/** @var array<int, StoredToken> */
	public array $rows = [];

	private \DateTimeImmutable $now;

	public function __construct() {
		$this->now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
	}

	public function create(
		string $label,
		GrantedScopeSet $scopes,
		int $owner_user_id,
		?\DateTimeImmutable $expires_at,
		?string $client_id = null,
		?string $audience = null,
		?\DateTimeImmutable $refresh_expires_at = null,
		array $approved_tools = []
	): IssuedTokens {
		$id       = count( $this->rows ) + 1;
		$token_id = TokenId::generate();
		$secret   = TokenSecret::generate();
		$refresh  = null !== $refresh_expires_at ? TokenSecret::generate() : null;

		$this->rows[ $id ] = new StoredToken(
			new ApiToken( $id, $token_id, $label, $scopes, TokenStatus::Active, $owner_user_id, new \DateTimeImmutable(), null, $expires_at, $client_id, $audience, $refresh_expires_at, $approved_tools ),
			$secret->hash(),
			$refresh?->hash(),
			null,
			null !== $refresh ? $this->now : null
		);

		return new IssuedTokens( PlainToken::compose( $token_id, $secret ), $expires_at ?? new \DateTimeImmutable(), null !== $refresh ? PlainRefreshToken::compose( $token_id, $refresh ) : null );
	}

	/** Set by a test to play a request that rotated between this one's read and write. */
	public ?\Closure $before_rotate = null;

	public function rotate( ApiToken $token, TokenSecret $retired_refresh, \DateTimeImmutable $access_expires_at ): ?IssuedTokens {
		if ( null !== $this->before_rotate ) {
			$interleaved         = $this->before_rotate;
			$this->before_rotate = null;
			$interleaved();
		}

		$stored = $this->rows[ $token->id ];

		if ( $stored->refresh_secret_hash !== $retired_refresh->hash() ) {
			return null;
		}

		$issued = new IssuedTokens(
			PlainToken::compose( $token->token_id, TokenSecret::generate() ),
			$access_expires_at,
			PlainRefreshToken::compose( $token->token_id, TokenSecret::generate() )
		);

		$this->rows[ $token->id ] = new StoredToken(
			$this->with( $stored->token, [ 'expires_at' => $access_expires_at ] ),
			$issued->access->secret->hash(),
			$issued->refresh?->secret->hash(),
			$stored->refresh_secret_hash,
			$this->now,
			$stored->secret_hash,
			SealedRotation::seal( $issued, $retired_refresh )
		);

		return $issued;
	}

	/** Lets a test place a rotation in the past, to step outside the grace window. */
	public function rotated_at( int $id, \DateTimeImmutable $when ): void {
		$stored = $this->rows[ $id ];

		$this->rows[ $id ] = new StoredToken( $stored->token, $stored->secret_hash, $stored->refresh_secret_hash, $stored->previous_refresh_secret_hash, $when, $stored->previous_secret_hash, $stored->rotation );
	}

	public function forget_rotation( int $id ): void {
		$stored = $this->rows[ $id ];

		$this->rows[ $id ] = new StoredToken( $stored->token, $stored->secret_hash, $stored->refresh_secret_hash, $stored->previous_refresh_secret_hash, $stored->rotated_at );
	}

	public function find_active_by_token_id( TokenId $token_id ): ?StoredToken {
		foreach ( $this->rows as $stored ) {
			if ( $stored->token->token_id->value === $token_id->value && TokenStatus::Active === $stored->token->status ) {
				return $stored;
			}
		}

		return null;
	}

	public function list_all(): array {
		return array_map( static fn ( StoredToken $stored ): ApiToken => $stored->token, array_values( $this->rows ) );
	}

	public function revoke( int $id ): bool {
		$this->set_status( $id, TokenStatus::Revoked );

		return true;
	}

	public function mark_expired( int $id ): void {
		$this->set_status( $id, TokenStatus::Expired );
	}

	public function touch_last_used( int $id ): void {}

	private function set_status( int $id, TokenStatus $status ): void {
		$stored = $this->rows[ $id ];

		$this->rows[ $id ] = new StoredToken( $this->with( $stored->token, [ 'status' => $status ] ), $stored->secret_hash, $stored->refresh_secret_hash, $stored->previous_refresh_secret_hash, $stored->rotated_at, $stored->previous_secret_hash, $stored->rotation );
	}

	/** @param array<string, mixed> $changes */
	private function with( ApiToken $token, array $changes ): ApiToken {
		return new ApiToken(
			$token->id,
			$token->token_id,
			$token->label,
			$token->scopes,
			$changes['status'] ?? $token->status,
			$token->owner_user_id,
			$token->created_at,
			$token->last_used_at,
			$changes['expires_at'] ?? $token->expires_at,
			$token->client_id,
			$token->audience,
			$token->refresh_expires_at
		);
	}
}
