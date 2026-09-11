<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * What one token-endpoint response carries: the plain secrets exist only here,
 * on their way to the client.
 */
final readonly class IssuedTokens {

	public function __construct(
		public PlainToken $access,
		public \DateTimeImmutable $access_expires_at,
		public ?PlainRefreshToken $refresh,
	) {}

	public function expires_in( \DateTimeImmutable $now ): int {
		return max( 0, $this->access_expires_at->getTimestamp() - $now->getTimestamp() );
	}
}
