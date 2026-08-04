<?php
/**
 * Minimal declarations for the bundled Freemius SDK, for both tests and PHPStan.
 *
 * The SDK ships in /freemius, which is outside the analysed paths — it is
 * vendor code we neither wrote nor lint, and pulling it into the baseline would
 * bury our own findings under thousands of its own. Only the surface
 * FreemiusLicence touches is declared here.
 *
 * @see https://github.com/Freemius/wordpress-sdk
 */

declare( strict_types=1 );

// phpcs:disable

if ( ! class_exists( 'Freemius' ) ) {
	class Freemius {

		/** True for a paying customer, an active trial, or a developer install. */
		public function can_use_premium_code(): bool {
			return false;
		}

		public function get_upgrade_url(): string {
			return '';
		}

		public function get_account_url(): string {
			return '';
		}
	}
}

if ( ! function_exists( 'fs_dynamic_init' ) ) {
	/**
	 * @param array<string, mixed> $config
	 */
	function fs_dynamic_init( array $config ): Freemius {
		return new Freemius();
	}
}
