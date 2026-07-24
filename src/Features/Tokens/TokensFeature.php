<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Tokens;

use AgentGateMcp\Features\Tokens\Domain\TokenRepositoryInterface;
use AgentGateMcp\Features\Tokens\Persistence\Schema;
use AgentGateMcp\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap for the Tokens slice: schema upgrades now, admin screens in M3.
 */
final readonly class TokensFeature implements FeatureInterface {

	public function __construct( private TokenRepositoryInterface $repository ) {}

	public function register(): void {
		add_action( 'admin_init', [ Schema::class, 'maybe_upgrade' ] );
	}

	public function repository(): TokenRepositoryInterface {
		return $this->repository;
	}
}
