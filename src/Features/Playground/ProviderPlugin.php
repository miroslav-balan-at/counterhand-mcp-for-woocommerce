<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Playground;

defined( 'ABSPATH' ) || exit;

/**
 * The official wordpress.org provider plugins for the core AI Client.
 * Backed by the wordpress.org slug — the only value install accepts, so an
 * arbitrary slug from a request can never reach the installer.
 */
enum ProviderPlugin: string {

	case Anthropic = 'ai-provider-for-anthropic';
	case OpenAi    = 'ai-provider-for-openai';
	case Google    = 'ai-provider-for-google';

	public function label(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Anthropic => __( 'Claude (Anthropic)', 'agentgate-mcp-for-woocommerce' ),
			self::OpenAi    => __( 'ChatGPT (OpenAI)', 'agentgate-mcp-for-woocommerce' ),
			self::Google    => __( 'Gemini (Google)', 'agentgate-mcp-for-woocommerce' ),
		};
	}

	/** Plugin basename if installed (any version, active or not), else null. */
	public function installed_basename(): ?string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( get_plugins() ) as $basename ) {
			if ( str_starts_with( $basename, $this->value . '/' ) ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
				return $basename;
			}
		}

		return null;
	}

	public function is_active(): bool {
		$basename = $this->installed_basename(); // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.

		return null !== $basename && is_plugin_active( $basename );
	}

	public static function any_active(): bool {
		foreach ( self::cases() as $plugin ) {
			if ( $plugin->is_active() ) {
				return true;
			}
		}

		return false;
	}
}
