<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Persistence;

use Counterhand\Features\Tokens\Domain\ApiToken;
use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
use Counterhand\Features\Tokens\Domain\IssuedTokens;
use Counterhand\Features\Tokens\Domain\PlainRefreshToken;
use Counterhand\Features\Tokens\Domain\PlainToken;
use Counterhand\Features\Tokens\Domain\StoredToken;
use Counterhand\Features\Tokens\Domain\TokenId;
use Counterhand\Features\Tokens\Domain\TokenRepositoryInterface;
use Counterhand\Features\Tokens\Domain\TokenSecret;
use Counterhand\Features\Tokens\Domain\TokenStatus;

defined( 'ABSPATH' ) || exit;

final class WpdbTokenRepository implements TokenRepositoryInterface {

	public function create(
		string $label,
		GrantedScopeSet $scopes,
		int $owner_user_id,
		?\DateTimeImmutable $expires_at,
		?string $client_id = null,
		?string $audience = null,
		?\DateTimeImmutable $refresh_expires_at = null
	): IssuedTokens {
		global $wpdb;

		$token_id = TokenId::generate();
		$secret   = TokenSecret::generate();
		$refresh  = null !== $refresh_expires_at ? TokenSecret::generate() : null;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.
			Schema::table_name(),
			[
				'token_id'            => $token_id->value,
				'secret_hash'         => $secret->hash(),
				'label'               => $label,
				'scopes'              => $scopes->to_csv(),
				'status'              => TokenStatus::Active->value,
				'owner_user_id'       => $owner_user_id,
				'created_at'          => current_time( 'mysql', true ),
				'expires_at'          => $expires_at?->format( 'Y-m-d H:i:s' ),
				'client_id'           => $client_id,
				'audience'            => $audience,
				'refresh_secret_hash' => $refresh?->hash(),
				'rotated_at'          => null !== $refresh ? current_time( 'mysql', true ) : null,
				'refresh_expires_at'  => $refresh_expires_at?->format( 'Y-m-d H:i:s' ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return new IssuedTokens(
			PlainToken::compose( $token_id, $secret ),
			$expires_at ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ),
			null !== $refresh ? PlainRefreshToken::compose( $token_id, $refresh ) : null
		);
	}

	public function rotate( ApiToken $token, string $expected_refresh_hash, \DateTimeImmutable $access_expires_at ): ?IssuedTokens {
		global $wpdb;

		$secret     = TokenSecret::generate();
		$refresh    = TokenSecret::generate();
		$table_name = Schema::table_name();

		// One statement, so the outgoing hash moves to previous_* in the same
		// write that replaces it — a replayed old token can then be recognised.
		// Conditioned on the hash just verified, so of two requests racing with
		// the same refresh token exactly one rotates and the loser knows it lost.
		$rotated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.
			$wpdb->prepare(
				"UPDATE {$table_name} SET previous_refresh_secret_hash = refresh_secret_hash, refresh_secret_hash = %s, secret_hash = %s, expires_at = %s, rotated_at = %s WHERE id = %d AND refresh_secret_hash = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-owned.
				$refresh->hash(),
				$secret->hash(),
				$access_expires_at->format( 'Y-m-d H:i:s' ),
				current_time( 'mysql', true ),
				$token->id,
				$expected_refresh_hash
			)
		);

		if ( 1 !== $rotated ) {
			return null;
		}

		return new IssuedTokens(
			PlainToken::compose( $token->token_id, $secret ),
			$access_expires_at,
			PlainRefreshToken::compose( $token->token_id, $refresh )
		);
	}

	public function find_active_by_token_id( TokenId $token_id ): ?StoredToken {
		global $wpdb;

		$table_name = Schema::table_name();

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE token_id = %s AND status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-owned.
				$token_id->value,
				TokenStatus::Active->value
			),
			ARRAY_A
		);

		if ( null === $row ) {
			return null;
		}

		return new StoredToken(
			$this->hydrate( $row ),
			(string) $row['secret_hash'],
			isset( $row['refresh_secret_hash'] ) && $row['refresh_secret_hash'] ? (string) $row['refresh_secret_hash'] : null,
			isset( $row['previous_refresh_secret_hash'] ) && $row['previous_refresh_secret_hash'] ? (string) $row['previous_refresh_secret_hash'] : null,
			isset( $row['rotated_at'] ) && $row['rotated_at'] ? new \DateTimeImmutable( (string) $row['rotated_at'], new \DateTimeZone( 'UTC' ) ) : null
		);
	}

	public function list_all(): array {
		global $wpdb;

		$table_name = Schema::table_name();

		$rows = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY created_at DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name is plugin-owned, no user input. plugin-owned table, no core API covers it.

		return array_map( fn ( array $row ): ApiToken => $this->hydrate( $row ), is_array( $rows ) ? $rows : [] );
	}

	public function revoke( int $id ): bool {
		global $wpdb;

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.
			Schema::table_name(),
			[
				'status'     => TokenStatus::Revoked->value,
				'revoked_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $updated && $updated > 0;
	}

	public function mark_expired( int $id ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.
			Schema::table_name(),
			[ 'status' => TokenStatus::Expired->value ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	public function touch_last_used( int $id ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.
			Schema::table_name(),
			[ 'last_used_at' => current_time( 'mysql', true ) ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	private function hydrate( array $row ): ApiToken {
		return new ApiToken(
			id: (int) $row['id'],
			token_id: TokenId::try_from_string( (string) $row['token_id'] ) ?? throw new \RuntimeException( 'Corrupt token_id in storage.' ),
			label: (string) $row['label'],
			scopes: GrantedScopeSet::from_csv( (string) $row['scopes'] ),
			status: TokenStatus::from( (string) $row['status'] ),
			owner_user_id: (int) $row['owner_user_id'],
			created_at: new \DateTimeImmutable( (string) $row['created_at'], new \DateTimeZone( 'UTC' ) ),
			last_used_at: isset( $row['last_used_at'] ) && $row['last_used_at'] ? new \DateTimeImmutable( (string) $row['last_used_at'], new \DateTimeZone( 'UTC' ) ) : null,
			expires_at: isset( $row['expires_at'] ) && $row['expires_at'] ? new \DateTimeImmutable( (string) $row['expires_at'], new \DateTimeZone( 'UTC' ) ) : null,
			client_id: isset( $row['client_id'] ) && $row['client_id'] ? (string) $row['client_id'] : null,
			audience: isset( $row['audience'] ) && $row['audience'] ? (string) $row['audience'] : null,
			refresh_expires_at: isset( $row['refresh_expires_at'] ) && $row['refresh_expires_at'] ? new \DateTimeImmutable( (string) $row['refresh_expires_at'], new \DateTimeZone( 'UTC' ) ) : null,
		);
	}
}
