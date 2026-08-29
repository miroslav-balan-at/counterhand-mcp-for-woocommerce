<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Persistence;

use Counterhand\Features\Tokens\Domain\ApiToken;
use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
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
		?string $audience = null
	): PlainToken {
		global $wpdb;

		$token_id = TokenId::generate();
		$secret   = TokenSecret::generate();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.
			Schema::table_name(),
			[
				'token_id'      => $token_id->value,
				'secret_hash'   => $secret->hash(),
				'label'         => $label,
				'scopes'        => $scopes->to_csv(),
				'status'        => TokenStatus::Active->value,
				'owner_user_id' => $owner_user_id,
				'created_at'    => current_time( 'mysql', true ),
				'expires_at'    => $expires_at?->format( 'Y-m-d H:i:s' ),
				'client_id'     => $client_id,
				'audience'      => $audience,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
		);

		return PlainToken::compose( $token_id, $secret );
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

		return new StoredToken( $this->hydrate( $row ), (string) $row['secret_hash'] );
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
		);
	}
}
