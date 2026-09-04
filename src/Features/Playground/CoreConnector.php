<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

defined( 'ABSPATH' ) || exit;

/**
 * One AI provider registered with WordPress 7.0's Connectors API.
 *
 * Read-only view over the public wp_get_connectors() data, so the chooser can
 * name the providers the site has and say which of them WordPress already
 * accepts a key for. The key itself is entered, stored and validated on
 * core's own Settings → Connectors screen; this plugin never reads or writes
 * the connector's credential.
 */
final readonly class CoreConnector {

	public function __construct(
		public string $id,
		public string $name,
		public string $credentials_url,
		public bool $is_connected,
	) {}

	/** Active AI providers WordPress can hold a key for. */
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

			// Only providers whose plugin is actually active can be configured.
			$is_active = $data['plugin']['is_active'] ?? null;

			if ( is_callable( $is_active ) && ! $is_active() ) {
				continue;
			}

			$connectors[] = new self(
				id: (string) $id,
				name: (string) ( $data['name'] ?? $id ),
				credentials_url: (string) ( $auth['credentials_url'] ?? '' ),
				is_connected: self::provider_accepts_key( (string) $id ),
			);
		}

		return $connectors;
	}

	/** Core's own screen for entering and rotating connector keys. */
	public static function settings_url(): string {
		return admin_url( 'options-connectors.php' );
	}

	/**
	 * Whether the AI client accepts the stored key, which is the same signal
	 * core's own Connectors screen shows as "connected". Asked of the client
	 * rather than the option so the credential never passes through here.
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
}
