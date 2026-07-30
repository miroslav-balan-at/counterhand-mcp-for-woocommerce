<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Shared\CanonicalUri;
use Counterhand\Shared\FeatureInterface;
use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * Admin shell: the plugin's menu and screens, the Settings API registration,
 * and the Connect screen's readiness and connection checks.
 */
final readonly class SettingsFeature implements FeatureInterface {

	private const CAPABILITY = 'manage_woocommerce';

	private ScreenAssets $assets;

	public function __construct(
		private PluginSettings $settings,
		private SettingsTabInterface $connections_tab,
		private SettingsTabInterface $log_tab,
		private SettingsTabInterface $chat_tab,
		private ConnectReadiness $readiness,
		private ConnectionMatcher $matcher,
		private SettingSanitizer $sanitizer,
	) {
		$this->assets = new ScreenAssets();
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this->assets, 'enqueue' ] );
		add_action( 'wp_ajax_ctrh_preflight', [ $this, 'handle_preflight' ] );
		add_action( 'wp_ajax_ctrh_connection_status', [ $this, 'handle_connection_status' ] );
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
			__( 'Counterhand MCP', 'counterhand-mcp-for-woocommerce' ),
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
			__( 'AI Chat', 'counterhand-mcp-for-woocommerce' ),
			self::CAPABILITY,
			$chat->value
		);
	}

	public function render_chat(): void {
		$this->render( AdminScreen::Chat, fn () => $this->chat_tab->render_tab() );
	}

	public function render_connect(): void {
		$this->render(
			AdminScreen::Connect,
			function (): void {
				$active = ConnectTab::current();
				$counts = [ ConnectTab::Connections->value => $this->matcher->live_count() ];

				require __DIR__ . '/views/connect-tabs.php';

				if ( ConnectTab::Connections === $active ) {
					$this->connections_tab->render_tab();

					return;
				}

				$endpoint_url      = CanonicalUri::mcp();
				$fallback_url      = rest_url( 'counterhand/v1/mcp' );
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
		$this->render( AdminScreen::Log, fn () => $this->log_tab->render_tab() );
	}

	/** Shared page chrome: heading, subtitle, then the screen's own body. */
	private function render( AdminScreen $screen, callable $body ): void {
		printf(
			'<div class="wrap ctrh-wrap%s"><h1>%s</h1><p class="ctrh-subtitle">%s</p>',
			$screen->is_full_bleed() ? ' ctrh-wrap--chat' : '',
			esc_html( $screen->page_title() ),
			esc_html( $screen->subtitle() )
		);

		$body();

		echo '</div>';
	}

	public function register_settings(): void {
		register_setting(
			'ctrh_settings_group',
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

	/**
	 * Readiness check for the Connect tab, run automatically on load.
	 *
	 * Replaces the old manual verifier: an admin should not have to press a
	 * button to discover that their store cannot be reached.
	 */
	public function handle_preflight(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Not allowed.', 'counterhand-mcp-for-woocommerce' ) ], 403 );
		}

		check_ajax_referer( 'ctrh_connect' );

		wp_send_json_success( $this->readiness->check()->to_array() );
	}

	/**
	 * Whether a connection landed since the admin left for the app's site, so
	 * the client card can confirm itself in place.
	 */
	public function handle_connection_status(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Not allowed.', 'counterhand-mcp-for-woocommerce' ) ], 403 );
		}

		check_ajax_referer( 'ctrh_connect' );

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
