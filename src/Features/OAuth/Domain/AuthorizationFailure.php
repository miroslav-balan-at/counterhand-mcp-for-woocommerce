<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth\Domain;

defined( 'ABSPATH' ) || exit;

/** An authorize request that cannot go ahead, and where its client said to hear about that. */
final readonly class AuthorizationFailure {

	public function __construct(
		public string $client_id,
		public string $redirect_uri,
		public string $error,
		public string $state,
	) {}
}
