<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * A token row loaded for verification: metadata plus the stored secret hash.
 */
final readonly class StoredToken {

	/**
	 * How long the secrets a rotation retired still answer.
	 *
	 * Clients refresh proactively and on a 401 at once, and several processes
	 * of one client share a credential, so requests carrying the retired
	 * secrets are still in flight. Kept at the 60 seconds the OAuth working
	 * group names as the ceiling for a grace period.
	 */
	public const ROTATION_GRACE_SECONDS = 60;

	public function __construct(
		public ApiToken $token,
		public string $secret_hash,
		public ?string $refresh_secret_hash = null,
		public ?string $previous_refresh_secret_hash = null,
		public ?\DateTimeImmutable $rotated_at = null,
		public ?string $previous_secret_hash = null,
		public ?SealedRotation $rotation = null,
	) {}

	/** A rotation's leftovers are still stored though nothing may use them any more. */
	public function holds_expired_rotation( \DateTimeImmutable $now ): bool {
		return ( null !== $this->rotation || null !== $this->previous_secret_hash )
			&& ! $this->is_within_rotation_grace( $now, self::ROTATION_GRACE_SECONDS );
	}

	/**
	 * The current access secret, or the one the last rotation retired while
	 * requests sent with it can still be arriving.
	 */
	public function accepts_access_secret( string $presented_hash, \DateTimeImmutable $now ): bool {
		if ( hash_equals( $this->secret_hash, $presented_hash ) ) {
			return true;
		}

		return null !== $this->previous_secret_hash
			&& hash_equals( $this->previous_secret_hash, $presented_hash )
			&& $this->is_within_rotation_grace( $now, self::ROTATION_GRACE_SECONDS );
	}

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
