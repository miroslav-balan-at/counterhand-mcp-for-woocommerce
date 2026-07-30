<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Turns whatever the settings form posted into the option payload.
 *
 * Keys are whitelisted by PluginSettings::defaults(), so an unknown key never
 * persists and a group added to the taxonomy is accepted without touching this
 * class. The default's PHP type decides how a value is read: bool means
 * checkbox, int means bounded number.
 */
final readonly class SettingSanitizer {

	/**
	 * Bounds per numeric key, matching the min/max the form advertises.
	 * Anything numeric without an entry falls back to the widest safe range.
	 */
	private const RANGES = [
		'rate_limit_per_minute' => [ 1, 1000 ],
		'log_retention_days'    => [ 1, 365 ],
	];

	private const FALLBACK_RANGE = [ 1, 1000 ];

	public function sanitize( mixed $raw ): array {
		$posted    = is_array( $raw ) ? $raw : [];
		$sanitized = [];

		foreach ( PluginSettings::defaults() as $key => $default_value ) {
			$sanitized[ $key ] = is_bool( $default_value )
				? ! empty( $posted[ $key ] )
				: $this->clamp( (string) $key, $posted[ $key ] ?? $default_value );
		}

		return $sanitized;
	}

	private function clamp( string $key, mixed $value ): int {
		[ $min, $max ] = self::RANGES[ $key ] ?? self::FALLBACK_RANGE;

		return max( $min, min( $max, (int) $value ) );
	}
}
