<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Typed reader for the single ctrh_settings option.
 * The admin UI (Settings slice) writes it; every other slice only reads.
 */
final class PluginSettings {

	public const OPTION = 'ctrh_settings';

	private ?array $cached = null;

	public function is_enabled(): bool {
		return (bool) $this->get( 'enabled' );
	}

	public function rate_limit_per_minute(): int {
		return max( 1, (int) $this->get( 'rate_limit_per_minute' ) );
	}

	public function is_action_log_enabled(): bool {
		return (bool) $this->get( 'action_log_enabled' );
	}

	public function log_retention_days(): int {
		return max( 1, (int) $this->get( 'log_retention_days' ) );
	}

	public function is_group_read_enabled( ToolGroup $group ): bool {
		return (bool) $this->get( $group->read_option_key() );
	}

	public function is_group_write_enabled( ToolGroup $group ): bool {
		// Read-only groups have no write key at all; treat as disabled.
		return (bool) ( $this->all()[ $group->write_option_key() ] ?? false );
	}

	public function all(): array {
		if ( null === $this->cached ) {
			$stored       = get_option( self::OPTION, [] );
			$this->cached = array_merge( self::defaults(), is_array( $stored ) ? $stored : [] );
		}

		return $this->cached;
	}

	/**
	 * The shipped option payload, computed from the tool taxonomy.
	 *
	 * Deriving it means a new ToolGroup brings its own keys along: sanitize_settings()
	 * iterates this, and all() merges it over whatever is stored, so existing
	 * installs pick the keys up on read with no migration and no version bump.
	 */
	public static function defaults(): array {
		return [
			'enabled'               => false,
			...self::group_defaults(),
			'rate_limit_per_minute' => 60,
			'action_log_enabled'    => false,
			'log_retention_days'    => 30,
		];
	}

	/** @return array<string, bool> */
	private static function group_defaults(): array {
		$defaults = [];

		foreach ( ToolGroup::cases() as $group ) {
			$defaults[ $group->read_option_key() ] = $group->enabled_by_default();

			if ( $group->has_write() ) {
				// Writes never ship on, whatever the group.
				$defaults[ $group->write_option_key() ] = false;
			}
		}

		return $defaults;
	}

	private function get( string $key ): mixed {
		return $this->all()[ $key ] ?? null;
	}
}
