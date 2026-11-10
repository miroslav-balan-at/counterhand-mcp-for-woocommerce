<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Tokens\Domain;

use Counterhand\Features\Tokens\Domain\ApiToken;
use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
use Counterhand\Features\Tokens\Domain\TokenId;
use Counterhand\Features\Tokens\Domain\TokenStatus;
use Counterhand\Tests\Unit\TestCase;

/** The stored status turns to expired only when the token is next tried, so a list must not read it alone. */
final class ApiTokenStatusTest extends TestCase {

	private function token( TokenStatus $status, ?string $refresh_expires_at ): ApiToken {
		return new ApiToken(
			id: 1,
			token_id: TokenId::try_from_string( 'statuscheck00000' ),
			label: 'test',
			scopes: GrantedScopeSet::from_values( [ 'products:read' ] ),
			status: $status,
			owner_user_id: 1,
			created_at: new \DateTimeImmutable( '2026-08-01' ),
			last_used_at: null,
			expires_at: new \DateTimeImmutable( '2026-08-01 01:00' ),
			refresh_expires_at: null === $refresh_expires_at ? null : new \DateTimeImmutable( $refresh_expires_at ),
		);
	}

	public function test_an_active_token_past_its_refresh_window_reads_as_expired(): void {
		$now = new \DateTimeImmutable( '2026-11-10' );

		$this->assertSame( TokenStatus::Expired, $this->token( TokenStatus::Active, '2026-08-31' )->status_at( $now ) );
		$this->assertSame( TokenStatus::Active, $this->token( TokenStatus::Active, '2026-12-01' )->status_at( $now ) );
		$this->assertSame( TokenStatus::Revoked, $this->token( TokenStatus::Revoked, '2026-08-31' )->status_at( $now ) );
	}
}
