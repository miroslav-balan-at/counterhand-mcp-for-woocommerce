<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Doubles;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Tokens\Domain\ApiToken;
use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
use Counterhand\Features\Tokens\Domain\TokenId;
use Counterhand\Features\Tokens\Domain\TokenStatus;

/**
 * Builds an AuthenticatedAgent granting exactly the given scopes.
 */
final class AgentFactory {

	/** @param list<string> $scopes */
	public static function with_scopes( array $scopes ): AuthenticatedAgent {
		return new AuthenticatedAgent(
			new ApiToken(
				1,
				TokenId::try_from_string( 'abcdef0123456789' ),
				'Test token',
				GrantedScopeSet::from_values( $scopes ),
				TokenStatus::Active,
				1,
				new \DateTimeImmutable( '2026-01-01 00:00:00' ),
				null,
				null
			)
		);
	}
}
