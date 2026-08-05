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
	public function hit( string $token_id ): void {
		/**
		 * Filters the per-token request budget per 60-second window.
		 *
		 * @param int    $limit    Requests allowed per window.
		 * @param string $token_id Public token id being limited.
		 */
		$limit = (int) apply_filters( 'counterhand_rate_limit', $this->settings->rate_limit_per_minute(), $token_id );

		$transient_key = 'counterhand_rl_' . $token_id;
		$current_count = (int) get_transient( $transient_key );

		if ( $current_count >= $limit ) {
			throw new RateLimitExceededException( self::WINDOW_SECONDS ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
		}

		if ( 0 === $current_count ) {
			set_transient( $transient_key, 1, self::WINDOW_SECONDS );
			return;
		}

		set_transient( $transient_key, $current_count + 1, self::WINDOW_SECONDS );
	}
}
