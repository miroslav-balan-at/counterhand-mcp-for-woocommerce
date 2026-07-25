<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Settings;

use AgentGateMcp\Features\ActionLog\ActionLogFeature;
use AgentGateMcp\Features\Playground\PlaygroundFeature;
use AgentGateMcp\Features\Tokens\Admin\ConnectionsAdmin;
use AgentGateMcp\Shared\FeatureInterface;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Admin shell: menu entry under WooCommerce, tab router, Settings API,
 * page-gated assets, and the connection verifier.
 */
final readonly class SettingsFeature implements FeatureInterface {

	public const PAGE_SLUG = 'agentgate-mcp';

	public function __construct(
		private PluginSettings $settings,
		private ConnectionsAdmin $connections_admin,
		private ActionLogFeature $action_log,
		private PlaygroundFeature $playground,
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_agmcp_verify_connection', [ $this, 'handle_verify_connection' ] );
		add_action( 'admin_post_agmcp_save_chat', [ $this, 'handle_save_chat' ] );
	}

	/** Saves the Chat tab's provider/model/key — separate from the tool settings. */
	public function handle_save_chat(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'agentgate-mcp-for-woocommerce' ) );
		}

		check_admin_referer( 'agmcp_save_chat' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$settings = $this->playground->settings();

		if ( isset( $_POST['agmcp_chat_forget'] ) ) {
			$settings->forget_key();
		}

		$submitted_key = isset( $_POST['agmcp_chat_key'] ) ? sanitize_text_field( wp_unslash( $_POST['agmcp_chat_key'] ) ) : '';

		$settings->save(
			sanitize_key( wp_unslash( $_POST['agmcp_chat_provider'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['agmcp_chat_model'] ?? '' ) ),
			esc_url_raw( wp_unslash( $_POST['agmcp_chat_base_url'] ?? '' ) ),
			$submitted_key
		);
		// phpcs:enable

		wp_safe_redirect(
			add_query_arg(
				[
					'page'             => self::PAGE_SLUG,
					'tab'              => 'settings',
					'agmcp_chat_saved' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'AgentGate MCP', 'agentgate-mcp-for-woocommerce' ),
			__( 'AgentGate MCP', 'agentgate-mcp-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		register_setting(
			'agmcp_settings_group',
			PluginSettings::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => PluginSettings::defaults(),
			]
		);
	}

	/** Whitelist keys and cast types — unknown keys never persist. */
	public function sanitize_settings( mixed $raw ): array {
		$raw       = is_array( $raw ) ? $raw : [];
		$sanitized = [];

		foreach ( PluginSettings::defaults() as $key => $default_value ) {
			$sanitized[ $key ] = is_bool( $default_value )
				? ! empty( $raw[ $key ] )
				: max( 1, min( 1000, (int) ( $raw[ $key ] ?? $default_value ) ) );
		}

		return $sanitized;
	}

	public function render_page(): void {
		// The playground is the plugin's centrepiece, so it is the landing tab.
		$active_tab = sanitize_key( $_GET['tab'] ?? 'playground' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab routing only.
		if ( ! in_array( $active_tab, [ 'playground', 'connect', 'connections', 'log', 'settings' ], true ) ) {
			$active_tab = 'playground';
		}

		$settings       = $this->settings;
		$tool_groups    = ToolGroup::cases();
		$endpoint_url   = home_url( '/mcp' );
		$fallback_url   = rest_url( 'agentgate/v1/mcp' );
		$verify_nonce   = wp_create_nonce( 'agmcp_verify_connection' );
		$chat_settings  = $this->playground->settings();
		$chat_providers = $this->playground->providers()->all();

		include __DIR__ . '/views/page.php';

		match ( $active_tab ) {
			'connect'     => require __DIR__ . '/views/tab-connect.php',
			'connections' => $this->connections_admin->render_tab(),
			'log'         => $this->action_log->render_tab(),
			'settings'    => require __DIR__ . '/views/tab-settings.php',
			default       => $this->playground->render_tab(),
		};

		echo '</div>'; // closes .wrap opened in page.php
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$base_url  = plugins_url( '', AGMCP_PLUGIN_FILE );
		$base_path = AGMCP_PLUGIN_DIR;

		wp_enqueue_style(
			'agmcp-admin',
			$base_url . '/assets/admin/settings.css',
			[],
			(string) filemtime( $base_path . '/assets/admin/settings.css' )
		);

		wp_enqueue_script(
			'agmcp-admin',
			$base_url . '/assets/admin/tokens.js',
			[],
			(string) filemtime( $base_path . '/assets/admin/tokens.js' ),
			true
		);

		// The chat bundle only loads on its own tab, which is also the default.
		$active_tab = sanitize_key( $_GET['tab'] ?? 'playground' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab routing only.
		if ( 'playground' !== $active_tab ) {
			return;
		}

		wp_enqueue_style(
			'agmcp-chat',
			$base_url . '/assets/admin/chat.css',
			[ 'agmcp-admin' ],
			(string) filemtime( $base_path . '/assets/admin/chat.css' )
		);

		wp_enqueue_script(
			'agmcp-chat',
			$base_url . '/assets/admin/chat.js',
			[],
			(string) filemtime( $base_path . '/assets/admin/chat.js' ),
			true
		);

		wp_localize_script(
			'agmcp-chat',
			'agmcpChat',
			[
				'i18n' => [
					'you'        => __( 'You', 'agentgate-mcp-for-woocommerce' ),
					'assistant'  => __( 'Assistant', 'agentgate-mcp-for-woocommerce' ),
					'thinking'   => __( 'Thinking…', 'agentgate-mcp-for-woocommerce' ),
					'failed'     => __( 'The request failed.', 'agentgate-mcp-for-woocommerce' ),
					'arguments'  => __( 'Arguments', 'agentgate-mcp-for-woocommerce' ),
					'result'     => __( 'Result', 'agentgate-mcp-for-woocommerce' ),
					'toolRan'    => __( 'ran', 'agentgate-mcp-for-woocommerce' ),
					'toolFailed' => __( 'failed', 'agentgate-mcp-for-woocommerce' ),
					'tokens'     => __( 'Tokens', 'agentgate-mcp-for-woocommerce' ),
				],
			]
		);
	}

	/**
	 * Endpoint verifier: the endpoint must be reachable and answer an
	 * unauthenticated request with a 401 carrying the OAuth discovery challenge.
	 */
	public function handle_verify_connection(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Not allowed.', 'agentgate-mcp-for-woocommerce' ) ], 403 );
		}

		check_ajax_referer( 'agmcp_verify_connection' );

		$response = wp_remote_post(
			home_url( '/mcp' ),
			[
				'headers'   => [ 'Content-Type' => 'application/json' ],
				'body'      => (string) wp_json_encode(
					[
						'jsonrpc' => '2.0',
						'id'      => 1,
						'method'  => 'initialize',
						'params'  => new \stdClass(),
					]
				),
				'timeout'   => 15,
				'sslverify' => apply_filters( 'agmcp_verify_sslverify', true ),
			]
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				[
					'message' => sprintf(
					/* translators: %s: error message */
						__( 'Endpoint not reachable: %s', 'agentgate-mcp-for-woocommerce' ),
						$response->get_error_message()
					),
				]
			);
		}

		$status    = (int) wp_remote_retrieve_response_code( $response );
		$challenge = (string) wp_remote_retrieve_header( $response, 'www-authenticate' );

		if ( 401 === $status && str_contains( $challenge, 'resource_metadata' ) ) {
			wp_send_json_success( [ 'message' => __( 'Endpoint is live and advertising OAuth discovery. Assistants can now connect.', 'agentgate-mcp-for-woocommerce' ) ] );
		}

		wp_send_json_error(
			[
				'message' => sprintf(
				/* translators: %d: HTTP status code */
					__( 'Unexpected response (status %d) — check that the connector is enabled and permalinks are working.', 'agentgate-mcp-for-woocommerce' ),
					$status
				),
			]
		);
	}
}
