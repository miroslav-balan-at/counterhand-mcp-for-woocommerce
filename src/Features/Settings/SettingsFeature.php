<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Settings;

use AgentGateMcp\Features\ActionLog\ActionLogFeature;
use AgentGateMcp\Features\Playground\PlaygroundFeature;
use AgentGateMcp\Features\Tokens\Admin\ConnectionsAdmin;
use AgentGateMcp\Shared\FeatureInterface;
use AgentGateMcp\Shared\StoreMark;
use AgentGateMcp\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * Admin shell: the plugin's menu and screens, the Settings API registration,
 * per-screen assets, and the Connect screen's readiness and connection checks.
 */
final readonly class SettingsFeature implements FeatureInterface {

	private const CAPABILITY = 'manage_woocommerce';

	public function __construct(
		private PluginSettings $settings,
		private ConnectionsAdmin $connections_admin,
		private ActionLogFeature $action_log,
		private PlaygroundFeature $playground,
		private ConnectReadiness $readiness,
		private ConnectionMatcher $matcher,
		private SettingSanitizer $sanitizer,
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_agmcp_preflight', [ $this, 'handle_preflight' ] );
		add_action( 'wp_ajax_agmcp_connection_status', [ $this, 'handle_connection_status' ] );
	}

	/**
	 * A top-level menu for the plugin's own screens, plus one WooCommerce entry
	 * pointing at the chat — that is the screen a shop manager opens daily, so
	 * it stays where they already work.
	 */
	public function add_menu(): void {
		$chat = AdminScreen::Chat;

		add_menu_page(
			$chat->page_title(),
			__( 'AgentGate MCP', 'agentgate-mcp-for-woocommerce' ),
			self::CAPABILITY,
			$chat->value,
			[ $this, 'render_chat' ],
			'dashicons-format-chat',
			56
		);

		$screens = [
			[ AdminScreen::Chat, [ $this, 'render_chat' ] ],
			[ AdminScreen::Connect, [ $this, 'render_connect' ] ],
			[ AdminScreen::Settings, [ $this, 'render_settings' ] ],
			[ AdminScreen::Log, [ $this, 'render_log' ] ],
		];

		foreach ( $screens as [$screen, $callback] ) {
			add_submenu_page(
				$chat->value,
				$screen->page_title(),
				$screen->menu_title(),
				self::CAPABILITY,
				$screen->value,
				$callback
			);
		}

		add_submenu_page(
			'woocommerce',
			$chat->page_title(),
			__( 'AI Chat', 'agentgate-mcp-for-woocommerce' ),
			self::CAPABILITY,
			$chat->value
		);
	}

	public function render_chat(): void {
		$this->render( AdminScreen::Chat, fn () => $this->playground->render_tab() );
	}

	public function render_connect(): void {
		$this->render(
			AdminScreen::Connect,
			function (): void {
				$active = ConnectTab::current();
				$counts = [ ConnectTab::Connections->value => $this->connections_admin->active_count() ];

				require __DIR__ . '/views/connect-tabs.php';

				if ( ConnectTab::Connections === $active ) {
					$this->connections_admin->render_tab();

					return;
				}

				$endpoint_url      = home_url( '/mcp' );
				$fallback_url      = rest_url( 'agentgate/v1/mcp' );
				$connect_clients   = McpClient::all( $endpoint_url );
				$connected_clients = $this->matcher->connected( $connect_clients );

				require __DIR__ . '/views/tab-connect.php';
			}
		);
	}

	public function render_settings(): void {
		$this->render(
			AdminScreen::Settings,
			function (): void {
				$settings      = $this->settings;
				$tool_sections = ToolSection::populated();

				require __DIR__ . '/views/tab-settings.php';
			}
		);
	}

	public function render_log(): void {
		$this->render( AdminScreen::Log, fn () => $this->action_log->render_tab() );
	}

	/** Shared page chrome: heading, subtitle, then the screen's own body. */
	private function render( AdminScreen $screen, callable $body ): void {
		printf(
			'<div class="wrap agmcp-wrap%s"><h1>%s</h1><p class="agmcp-subtitle">%s</p>',
			$screen->is_full_bleed() ? ' agmcp-wrap--chat' : '',
			esc_html( $screen->page_title() ),
			esc_html( $screen->subtitle() )
		);

		$body();

		echo '</div>';
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

	/** Registered as the Settings API sanitize_callback. */
	public function sanitize_settings( mixed $raw ): array {
		return $this->sanitizer->sanitize( $raw );
	}

	public function enqueue_assets(): void {
		$screen = $this->current_screen();

		if ( null === $screen ) {
			return;
		}

		$base_url  = plugins_url( '', AGMCP_PLUGIN_FILE );
		$base_path = AGMCP_PLUGIN_DIR;

		// Design tokens load first; every other sheet reads its custom properties.
		wp_enqueue_style(
			'agmcp-tokens',
			$base_url . '/assets/shared/tokens.css',
			[],
			(string) filemtime( $base_path . '/assets/shared/tokens.css' )
		);

		wp_enqueue_style(
			'agmcp-admin',
			$base_url . '/assets/admin/settings.css',
			[ 'agmcp-tokens' ],
			(string) filemtime( $base_path . '/assets/admin/settings.css' )
		);

		wp_enqueue_script(
			'agmcp-admin',
			$base_url . '/assets/admin/tokens.js',
			[],
			(string) filemtime( $base_path . '/assets/admin/tokens.js' ),
			true
		);

		if ( AdminScreen::Connect === $screen ) {
			if ( ConnectTab::Apps === ConnectTab::current() ) {
				$this->enqueue_connect( $base_url, $base_path );
			}

			return;
		}

		if ( AdminScreen::Chat === $screen ) {
			$this->enqueue_chat( $base_url, $base_path );
		}
	}

	/** Which of our screens is being rendered, or null when it is not ours. */
	private function current_screen(): ?AdminScreen {
		$page = sanitize_key( $_GET['page'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen routing only.

		return AdminScreen::tryFrom( $page );
	}

	private function enqueue_connect( string $base_url, string $base_path ): void {
		wp_enqueue_script(
			'agmcp-connect',
			$base_url . '/assets/admin/connect.js',
			[ 'agmcp-admin' ],
			(string) filemtime( $base_path . '/assets/admin/connect.js' ),
			true
		);

		wp_localize_script(
			'agmcp-connect',
			'agmcpConnect',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'agmcp_connect' ),
				'i18n'    => [
					'checking'    => __( 'Checking the store…', 'agentgate-mcp-for-woocommerce' ),
					'checkFailed' => __( 'The check could not be run.', 'agentgate-mcp-for-woocommerce' ),
					'waiting'     => __( 'Waiting for the app to connect…', 'agentgate-mcp-for-woocommerce' ),
					'connected'   => __( 'Connected', 'agentgate-mcp-for-woocommerce' ),
				],
			]
		);
	}

	private function enqueue_chat( string $base_url, string $base_path ): void {
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
				// The assistant speaks as the store, so it wears the store's
				// mark — the same one the OAuth consent screen shows.
				'avatar' => [
					'url'    => StoreMark::url() ?? '',
					'letter' => StoreMark::letter(),
				],
				'i18n'   => [
					'you'           => __( 'You', 'agentgate-mcp-for-woocommerce' ),
					'assistant'     => __( 'Assistant', 'agentgate-mcp-for-woocommerce' ),
					'thinking'      => __( 'Thinking…', 'agentgate-mcp-for-woocommerce' ),
					'failed'        => __( 'The request failed.', 'agentgate-mcp-for-woocommerce' ),
					'arguments'     => __( 'Arguments', 'agentgate-mcp-for-woocommerce' ),
					'result'        => __( 'Result', 'agentgate-mcp-for-woocommerce' ),
					'toolRan'       => __( 'ran', 'agentgate-mcp-for-woocommerce' ),
					'toolFailed'    => __( 'failed', 'agentgate-mcp-for-woocommerce' ),
					'tokens'        => __( 'Tokens', 'agentgate-mcp-for-woocommerce' ),
					'installing'    => __( 'Installing…', 'agentgate-mcp-for-woocommerce' ),
					'installed'     => __( 'Installed', 'agentgate-mcp-for-woocommerce' ),
					'installFailed' => __( 'The install failed.', 'agentgate-mcp-for-woocommerce' ),
				],
			]
		);
	}

	/**
	 * Readiness check for the Connect tab, run automatically on load.
	 *
	 * Replaces the old manual verifier: an admin should not have to press a
	 * button to discover that their store cannot be reached.
	 */
	public function handle_preflight(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Not allowed.', 'agentgate-mcp-for-woocommerce' ) ], 403 );
		}

		check_ajax_referer( 'agmcp_connect' );

		wp_send_json_success( $this->readiness->check()->to_array() );
	}

	/**
	 * Whether a connection landed since the admin left for the app's site, so
	 * the client card can confirm itself in place.
	 */
	public function handle_connection_status(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Not allowed.', 'agentgate-mcp-for-woocommerce' ) ], 403 );
		}

		check_ajax_referer( 'agmcp_connect' );

		$since = absint( $_POST['since'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$token = $this->matcher->newest_since( $since );

		wp_send_json_success(
			[
				'connected' => null !== $token,
				'client'    => $token->client_id ?? '',
			]
		);
	}
}
