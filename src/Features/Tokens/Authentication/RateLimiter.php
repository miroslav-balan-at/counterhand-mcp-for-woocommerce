<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Authentication;

use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Shared\Exception\RateLimitExceededException;

defined( 'ABSPATH' ) || exit;

/**
 * Per-token fixed-window rate limit backed by transients.
 */
final readonly class RateLimiter {

	private const WINDOW_SECONDS = 60;

	public function __construct( private PluginSettings $settings ) {}

	/**
	 * @throws RateLimitExceededException When the token exceeded its window budget.
	 */
	public function hit( string $token_id, int $requests = 1 ): void {
		/**
		 * Filters the per-token request budget per 60-second window.
		 *
		 * @param int    $limit    Requests allowed per window.
		 * @param string $token_id Public token id being limited.
		 */
		$limit = (int) apply_filters( 'counterhand_rate_limit', $this->settings->rate_limit_per_minute(), $token_id );

		// The window is in the key, because writing a transient restarts its expiry, which
		// would let a steady agent be refused while well under its per-minute budget.
		$now           = time();
		$transient_key = 'counterhand_rate_limit_' . $token_id . '_' . (int) floor( $now / self::WINDOW_SECONDS );
		$current_count = (int) get_transient( $transient_key );

		if ( $current_count + $requests > $limit ) {
			throw new RateLimitExceededException( self::WINDOW_SECONDS - ( $now % self::WINDOW_SECONDS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
		}

		set_transient( $transient_key, $current_count + $requests, 2 * self::WINDOW_SECONDS );
	}
}
