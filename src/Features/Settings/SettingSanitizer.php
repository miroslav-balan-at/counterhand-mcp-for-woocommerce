<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\Tokens\Domain\ScopeGroups;

defined( 'ABSPATH' ) || exit;

/**
 * Turns whatever the settings form posted into the option payload.
 *
 * Keys are whitelisted by PluginSettings::defaults(), so an unknown key never
 * persists and a group added to the taxonomy is accepted without touching this
 * class. The default's PHP type decides how a value is read: bool means
 * checkbox, int means bounded number.
 *
 * Two forms write this one option, and an unticked checkbox posts nothing, so each
 * form says which keys it owns. Otherwise saving one would switch off the other's,
 * the connector included. A write naming no form carries the whole option.
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
		$posted = is_array( $raw ) ? $raw : [];
		$form   = is_string( $posted['form'] ?? null ) ? SettingsForm::tryFrom( $posted['form'] ) : null;

		return match ( $form ) {
			SettingsForm::Integrations => [ ...$this->known_settings( array_merge( PluginSettings::defaults(), $this->stored() ) ), ...$this->ticked_integrations( $posted ) ],
			SettingsForm::Store        => [ ...$this->known_settings( $posted ), ...$this->stored_ticks_of_offered_plugins() ],
			null                       => [ ...$this->known_settings( $posted ), ...$this->ticked_integrations( $posted ) ],
		};
	}

	/**
	 * @param array<mixed> $values
	 * @return array<string, bool|int>
	 */
	private function known_settings( array $values ): array {
		$sanitized = [];

		foreach ( PluginSettings::defaults() as $key => $default_value ) {
			$sanitized[ $key ] = is_bool( $default_value )
				? ! empty( $values[ $key ] )
				: $this->clamp( (string) $key, $values[ $key ] ?? $default_value );
		}

		return $sanitized;
	}

	/**
	 * Not in defaults(): integrations come and go with their plugins.
	 *
	 * @param array<mixed> $values
	 * @return array<string, true>
	 */
	private function ticked_integrations( array $values ): array {
		$ticked = [];

		foreach ( $values as $key => $value ) {
			if ( is_string( $key ) && IntegrationGroup::is_option_key( $key ) && ! empty( $value ) ) {
				$ticked[ $key ] = true;
			}
		}

		return $ticked;
	}

	/**
	 * A plugin that is off when either form is saved loses its ticks, so reactivating
	 * it never quietly re-exposes it.
	 *
	 * @return array<string, true>
	 */
	private function stored_ticks_of_offered_plugins(): array {
		$offered = [];

		foreach ( ScopeGroups::integrations() as $group ) {
			$offered[ $group->read_option_key() ]  = true;
			$offered[ $group->write_option_key() ] = true;
		}

		return array_intersect_key( $this->ticked_integrations( $this->stored() ), $offered );
	}

	/** @return array<mixed> */
	private function stored(): array {
		$stored = get_option( PluginSettings::OPTION, [] );

		return is_array( $stored ) ? $stored : [];
	}

	private function clamp( string $key, mixed $value ): int {
		[ $min, $max ] = self::RANGES[ $key ] ?? self::FALLBACK_RANGE;

		return max( $min, min( $max, (int) $value ) );
	}
}
