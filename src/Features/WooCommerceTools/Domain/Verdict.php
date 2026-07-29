<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Whether a meta key may be touched, and if not, why.
 *
 * The reason is not decoration. A denial reaches an agent as the result of its
 * tool call, and an agent told only "denied" will try again with a variation;
 * one told which rule it hit, and that the rule is not negotiable, stops. The
 * wording is therefore written for a model to act on rather than for a log.
 */
final readonly class Verdict {

	private function __construct(
		public bool $allowed,
		public string $reason,
	) {}

	public static function allow(): self {
		return new self( true, '' );
	}

	public static function deny( string $reason ): self {
		return new self( false, $reason );
	}
}
