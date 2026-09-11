<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * A token row loaded for verification: metadata plus the stored secret hash.
 */
final readonly class StoredToken {

	public function __construct(
		public ApiToken $token,
		public string $secret_hash,
		public ?string $refresh_secret_hash = null,
		public ?string $previous_refresh_secret_hash = null,
		public ?\DateTimeImmutable $rotated_at = null,
	) {}

	/**
	 * Whether the just-retired refresh token is still inside the grace window.
	 *
	 * Clients refresh proactively and on a 401 at once, so two requests in
	 * flight legitimately carry the same token. Outside the window a reuse is
	 * what it looks like — a leak.
	 */
	public function is_within_rotation_grace( \DateTimeImmutable $now, int $grace_seconds ): bool {
		return null !== $this->rotated_at && ( $now->getTimestamp() - $this->rotated_at->getTimestamp() ) <= $grace_seconds;
	}
}
