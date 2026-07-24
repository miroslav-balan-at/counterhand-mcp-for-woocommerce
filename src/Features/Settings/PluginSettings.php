<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Settings;

use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Typed reader for the single agmcp_settings option.
 * The admin UI (Settings slice) writes it; every other slice only reads.
 */
final class PluginSettings {

	public const OPTION = 'agmcp_settings';

	private const DEFAULTS = [
		'enabled'               => false,
		'products_read'         => true,
		'products_write'        => false,
		'orders_read'           => true,
		'orders_write'          => false,
		'customers_read'        => false,
		'reports_read'          => true,
		'rate_limit_per_minute' => 60,
	];

	private ?array $cached = null;

	public function is_enabled(): bool {
		return (bool) $this->get( 'enabled' );
	}

	public function rate_limit_per_minute(): int {
		return max( 1, (int) $this->get( 'rate_limit_per_minute' ) );
	}

	public function is_group_read_enabled( ToolGroup $group ): bool {
		return (bool) $this->get( $group->value . '_read' );
	}

	public function is_group_write_enabled( ToolGroup $group ): bool {
		// Reports have no write axis; treat as disabled.
		return (bool) ( $this->all()[ $group->value . '_write' ] ?? false );
	}

	public function all(): array {
		if ( null === $this->cached ) {
			$stored       = get_option( self::OPTION, [] );
			$this->cached = array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : [] );
		}

		return $this->cached;
	}

	public static function defaults(): array {
		return self::DEFAULTS;
	}

	private function get( string $key ): mixed {
		return $this->all()[ $key ] ?? null;
	}
}
