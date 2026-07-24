<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * A token row loaded for verification: metadata plus the stored secret hash.
 */
final readonly class StoredToken {

	public function __construct(
		public ApiToken $token,
		public string $secret_hash,
	) {}
}
