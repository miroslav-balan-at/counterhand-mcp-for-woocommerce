<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens;

use Counterhand\Features\Tokens\Admin\ConnectionsAdmin;
use Counterhand\Features\Tokens\Domain\TokenRepositoryInterface;
use Counterhand\Features\Tokens\Persistence\Schema;
use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap for the Tokens slice: schema upgrades + connection management.
 */
final readonly class TokensFeature implements FeatureInterface {

	private ConnectionsAdmin $admin;

	public function __construct( private TokenRepositoryInterface $repository ) {
		$this->admin = new ConnectionsAdmin( $repository );
	}

	public function register(): void {
		add_action( 'admin_init', [ Schema::class, 'maybe_upgrade' ] );

		if ( is_admin() ) {
			$this->admin->register();
		}
	}

	public function admin(): ConnectionsAdmin {
		return $this->admin;
	}

	public function repository(): TokenRepositoryInterface {
		return $this->repository;
	}
}
