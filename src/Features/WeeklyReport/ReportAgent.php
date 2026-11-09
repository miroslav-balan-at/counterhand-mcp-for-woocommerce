<?php

declare( strict_types=1 );

namespace Counterhand\Features\WeeklyReport;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Features\Tokens\Domain\ApiToken;
use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
use Counterhand\Features\Tokens\Domain\TokenId;
use Counterhand\Features\Tokens\Domain\TokenStatus;

defined( 'ABSPATH' ) || exit;

/** A reader of reports and nothing else, for one person, under the store's own switches. */
final class ReportAgent {

	private const TOKEN_ID = 'weeklyreport0000';

	public static function for_user( int $user_id ): AuthenticatedAgent {
		return new AuthenticatedAgent(
			new ApiToken(
				id: 0,
				token_id: TokenId::try_from_string( self::TOKEN_ID ) ?? throw new \RuntimeException( 'Invalid report token id.' ),
				label: __( 'Weekly report', 'counterhand-mcp-for-woocommerce' ),
				scopes: GrantedScopeSet::from_values( [ ApiScope::ReportsRead->value ] ),
				status: TokenStatus::Active,
				owner_user_id: $user_id,
				created_at: new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ),
				last_used_at: null,
				expires_at: null,
			)
		);
	}
}
