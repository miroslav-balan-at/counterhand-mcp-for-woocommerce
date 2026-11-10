<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Rewrites;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Features\Tokens\Domain\ApiToken;
use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
use Counterhand\Features\Tokens\Domain\TokenId;
use Counterhand\Features\Tokens\Domain\TokenStatus;

defined( 'ABSPATH' ) || exit;

/**
 * The person who started a job, as a connection that reaches products and their undo
 * history and nothing else. The store's own switches still apply on top, so a job can
 * never write what the store keeps read-only. It shares the admin chat's token row,
 * so the same person sees these changes in either place.
 */
final class RewriteAgent {

	private const TOKEN_ID = 'bulkrewrites0000';

	public static function for_user( int $user_id ): AuthenticatedAgent {
		return new AuthenticatedAgent(
			new ApiToken(
				id: 0,
				token_id: TokenId::try_from_string( self::TOKEN_ID ) ?? throw new \RuntimeException( 'Invalid rewrite token id.' ),
				label: __( 'Bulk rewrites', 'counterhand-mcp-for-woocommerce' ),
				scopes: GrantedScopeSet::from_values(
					[
						ApiScope::ProductsRead->value,
						ApiScope::ProductsWrite->value,
						ApiScope::ChangesRead->value,
						ApiScope::ChangesWrite->value,
					]
				),
				status: TokenStatus::Active,
				owner_user_id: $user_id,
				created_at: new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ),
				last_used_at: null,
				expires_at: null,
			)
		);
	}
}
