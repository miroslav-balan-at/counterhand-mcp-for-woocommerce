<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

defined( 'ABSPATH' ) || exit;

/**
 * One AI provider registered with WordPress 7.0's Connectors API.
 *
 * Wraps the public wp_get_connectors() data so the key can be entered here
 * rather than sending the admin off to Settings → Connectors. The key still
 * lands in core's own registered setting, so it stays shared with every other
 * plugin and this one never keeps a copy.
 */
final readonly class CoreConnector {

	public function __construct(
		public string $id,
		public string $name,
		public string $setting_name,
		public string $credentials_url,
		public bool $has_key,
		public bool $is_connected,
	) {}

	/** Active AI providers whose key WordPress stores. */
	public static function ai_providers(): array {
		if ( ! function_exists( 'wp_get_connectors' ) ) {
			return [];
		}

		$connectors = [];

		foreach ( wp_get_connectors() as $id => $data ) {
			$auth = $data['authentication'] ?? [];

			if ( 'ai_provider' !== ( $data['type'] ?? '' ) || 'api_key' !== ( $auth['method'] ?? '' ) ) {
				continue;
			}

			$setting_name = (string) ( $auth['setting_name'] ?? '' );

			// No setting means core has no key to manage for this provider.
			if ( '' === $setting_name ) {
				continue;
			}

			// Only providers whose plugin is actually active can be configured.
			$is_active = $data['plugin']['is_active'] ?? null;

			if ( is_callable( $is_active ) && ! $is_active() ) {
				continue;
			}

			$connectors[] = new self(
				id: (string) $id,
				name: (string) ( $data['name'] ?? $id ),
				setting_name: $setting_name,
				credentials_url: (string) ( $auth['credentials_url'] ?? '' ),
				has_key: self::key_is_set( $setting_name, $auth ),
				is_connected: self::provider_accepts_key( (string) $id ),
			);
		}

		return $connectors;
	}

	public static function find( string $id ): ?self {
		foreach ( self::ai_providers() as $connector ) {
			if ( $connector->id === $id ) {
				return $connector;
			}
		}

		return null;
	}

	/** Writes to core's registered setting — the same place its own screen writes. */
	public function save_key( string $key ): void {
		update_option( $this->setting_name, $key );
	}

	/**
	 * Whether the AI client accepts the stored key, which is the same signal
	 * core's own Connectors screen shows as "connected". Distinguishes a key
	 * that is merely present from one that actually works.
	 */
	private static function provider_accepts_key( string $id ): bool {
		if ( ! class_exists( \WordPress\AiClient\AiClient::class ) ) {
			return false;
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();

			return $registry->hasProvider( $id ) && $registry->isProviderConfigured( $id );
		} catch ( \Throwable ) {
			return false;
		}
	}

	/**
	 * Mirrors core's env → constant → database precedence. Core's own resolver
	 * is a private function, so the check is repeated here from the public
	 * connector data rather than called.
	 *
	 * @param array<string, mixed> $auth
	 */
	private static function key_is_set( string $setting_name, array $auth ): bool {
		$env_var = (string) ( $auth['env_var_name'] ?? '' );

		if ( '' !== $env_var && '' !== (string) getenv( $env_var ) ) {
			return true;
		}

		$constant = (string) ( $auth['constant_name'] ?? '' );

		if ( '' !== $constant && defined( $constant ) && '' !== (string) constant( $constant ) ) {
			return true;
		}

		return '' !== (string) get_option( $setting_name, '' );
	}
}
