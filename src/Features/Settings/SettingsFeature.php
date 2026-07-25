<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Settings;

use AgentGateMcp\Features\ActionLog\ActionLogFeature;
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
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_agmcp_verify_connection', [ $this, 'handle_verify_connection' ] );
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
		$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab routing only.
		if ( ! in_array( $active_tab, [ 'settings', 'connections', 'connect', 'log' ], true ) ) {
			$active_tab = 'settings';
		}

		$settings     = $this->settings;
		$tool_groups  = ToolGroup::cases();
		$endpoint_url = home_url( '/mcp' );
		$fallback_url = rest_url( 'agentgate/v1/mcp' );
		$verify_nonce = wp_create_nonce( 'agmcp_verify_connection' );

		include __DIR__ . '/views/page.php';

		if ( 'connections' === $active_tab ) {
			$this->connections_admin->render_tab();
		} elseif ( 'connect' === $active_tab ) {
			include __DIR__ . '/views/tab-connect.php';
		} elseif ( 'log' === $active_tab ) {
			$this->action_log->render_tab();
		} else {
			include __DIR__ . '/views/tab-settings.php';
		}

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
