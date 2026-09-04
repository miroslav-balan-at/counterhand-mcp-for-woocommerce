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
		public bool $has_key,
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
				has_key: self::client_holds_key( (string) $id ),
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
	 * Whether core has handed the AI client a credential for this provider.
	 * Core's own screen calls this "connected", but it only means a key is
	 * saved — asked of the client, so the key itself never passes through here.
	 */
	private static function client_holds_key( string $id ): bool {
		$registry = self::registry();

		if ( null === $registry || ! $registry->hasProvider( $id ) ) {
			return false;
		}

		try {
			return null !== $registry->getProviderRequestAuthentication( $id );
		} catch ( \Throwable ) {
			return false;
		}
	}

	/**
	 * Whether the provider actually accepts that key. The client checks by
	 * listing the provider's models, so a revoked or mistyped key reports
	 * false here while core's screen still shows it as saved.
	 */
	private static function provider_accepts_key( string $id ): bool {
		$registry = self::registry();

		if ( null === $registry || ! $registry->hasProvider( $id ) ) {
			return false;
		}

		try {
			return $registry->isProviderConfigured( $id );
		} catch ( \Throwable ) {
			return false;
		}
	}

	private static function registry(): ?\WordPress\AiClient\ProviderRegistry {
		if ( ! class_exists( \WordPress\AiClient\AiClient::class ) ) {
			return null;
		}

		try {
			return \WordPress\AiClient\AiClient::defaultRegistry();
		} catch ( \Throwable ) {
			return null;
		}
	}
}
