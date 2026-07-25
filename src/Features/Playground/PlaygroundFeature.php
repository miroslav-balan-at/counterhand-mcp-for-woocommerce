<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Playground;

use AgentGateMcp\Features\McpServer\ToolRegistry;
use AgentGateMcp\Features\Playground\Provider\ProviderConfig;
use AgentGateMcp\Features\Playground\Provider\ProviderRegistry;
use AgentGateMcp\Features\Tokens\Authentication\AuthenticatedAgent;
use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Features\Tokens\Domain\ApiToken;
use AgentGateMcp\Features\Tokens\Domain\GrantedScopeSet;
use AgentGateMcp\Features\Tokens\Domain\TokenId;
use AgentGateMcp\Features\Tokens\Domain\TokenStatus;
use AgentGateMcp\Shared\Exception\ToolCallException;
use AgentGateMcp\Shared\FeatureInterface;
use AgentGateMcp\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The chat playground: talk to the store in plain language from wp-admin.
 *
 * The model runs server-side through AgentLoop, calling the same MCP tools an
 * external assistant would. No API token is involved — the caller is already an
 * authenticated administrator, so we build a synthetic in-memory agent scoped
 * to their own capabilities.
 */
final readonly class PlaygroundFeature implements FeatureInterface {

	private const NONCE = 'agmcp_chat_send';

	public function __construct(
		private ToolRegistry $tool_registry,
		private AgentLoop $loop,
		private ChatSettings $settings,
		private ProviderRegistry $providers,
	) {}

	public function register(): void {
		add_action( 'wp_ajax_agmcp_chat_send', [ $this, 'handle_send' ] );
	}

	public function settings(): ChatSettings {
		return $this->settings;
	}

	public function providers(): ProviderRegistry {
		return $this->providers;
	}

	public function render_tab(): void {
		$tools        = $this->tool_registry->visible_for( $this->synthetic_agent() );
		$is_ready     = $this->settings->is_configured() && '' !== $this->settings->api_key();
		$send_nonce   = wp_create_nonce( self::NONCE );
		$settings_url = admin_url( 'admin.php?page=agentgate-mcp&tab=settings' );

		include __DIR__ . '/views/tab-chat.php';
	}

	public function handle_send(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Not allowed.', 'agentgate-mcp-for-woocommerce' ) ], 403 );
		}

		check_ajax_referer( self::NONCE );

		$provider = $this->providers->get( $this->settings->provider_id() );
		if ( null === $provider ) {
			wp_send_json_error( [ 'message' => __( 'No chat provider is configured. Choose one on the Settings tab.', 'agentgate-mcp-for-woocommerce' ) ] );
		}

		$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		if ( '' === trim( $message ) ) {
			wp_send_json_error( [ 'message' => __( 'Type a message first.', 'agentgate-mcp-for-woocommerce' ) ] );
		}

		$history = json_decode( wp_unslash( $_POST['history'] ?? '[]' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- provider-format JSON validated below, never output as HTML.
		if ( ! is_array( $history ) ) {
			$history = [];
		}

		$config = new ProviderConfig(
			api_key: $this->settings->api_key(),
			model: $this->settings->model(),
			base_url: $this->settings->base_url(),
			system_prompt: $this->system_prompt(),
		);

		try {
			$result = $this->loop->run( $provider, $config, $history, $message, $this->synthetic_agent() );
		} catch ( ToolCallException $exception ) {
			wp_send_json_error( [ 'message' => $exception->getMessage() ] );
		} catch ( \Throwable $throwable ) {
			wp_send_json_error( [ 'message' => __( 'The chat request failed unexpectedly. Check the WooCommerce logs for details.', 'agentgate-mcp-for-woocommerce' ) ] );
		}

		wp_send_json_success(
			[
				'transcript' => $result['transcript'],
				'history'    => $result['messages'],
				'usage'      => $result['usage'],
			]
		);
	}

	private function system_prompt(): string {
		$tool_names = array_map(
			static fn ( ToolInterface $tool ): string => $tool->name(),
			$this->tool_registry->visible_for( $this->synthetic_agent() )
		);

		return sprintf(
			/* translators: 1: store name, 2: currency code, 3: comma-separated tool names */
			__( 'You are a WooCommerce store assistant for "%1$s". Prices are in %2$s. Use the available tools to answer questions and make changes — never guess at store data you can look up. Available tools: %3$s. New products are created as drafts for the administrator to review. Confirm before any destructive action. Answer concisely and mention the concrete records you touched.', 'agentgate-mcp-for-woocommerce' ),
			get_bloginfo( 'name' ),
			function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR',
			implode( ', ', $tool_names )
		);
	}

	/**
	 * An in-memory agent representing the current administrator. Scopes are the
	 * full catalogue because the admin already holds manage_woocommerce — the
	 * ToolRegistry still applies the store's group toggles on top.
	 */
	private function synthetic_agent(): AuthenticatedAgent {
		$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

		return new AuthenticatedAgent(
			new ApiToken(
				id: 0,
				token_id: TokenId::try_from_string( 'playground000000' ) ?? throw new \RuntimeException( 'Invalid playground token id.' ),
				label: __( 'Admin chat', 'agentgate-mcp-for-woocommerce' ),
				scopes: GrantedScopeSet::from_values( ApiScope::values() ),
				status: TokenStatus::Active,
				owner_user_id: get_current_user_id(),
				created_at: $now,
				last_used_at: null,
				expires_at: null,
			)
		);
	}
}
