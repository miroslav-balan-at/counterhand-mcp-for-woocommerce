<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Features\Playground\Provider\CoreAiClientProvider;
use Counterhand\Features\Playground\Provider\ProviderConfig;
use Counterhand\Features\Playground\Provider\ProviderRegistry;
use Counterhand\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

/**
 * The connect-a-model workflow: validate, prove the credentials work, then
 * save. Testing before storing is the point — a mistyped key fails here with
 * the provider's own error instead of on the first chat message.
 */
final readonly class ModelConnect {

	private const RESULT_TRANSIENT = 'ctrh_chat_save_result_';

	public function __construct(
		private ChatSettings $settings,
		private ProviderRegistry $providers,
	) {}

	public function register(): void {
		add_action( 'admin_post_ctrh_save_chat', [ $this, 'handle_save' ] );
		add_action( 'admin_post_ctrh_install_provider', [ $this, 'handle_install_provider' ] );
		add_action( 'wp_ajax_ctrh_install_provider', [ $this, 'handle_install_provider_ajax' ] );
		add_action( 'admin_post_ctrh_save_connector_key', [ $this, 'handle_save_connector_key' ] );
	}

	/** Which chooser card to show for the WordPress-managed path; null below 7.0. */
	public function core_state(): ?CoreAiState {
		$core = $this->providers->get( CoreAiClientProvider::ID );

		if ( null === $core ) {
			return null;
		}

		if ( $core->is_ready( new ProviderConfig( '', '', '', '' ) ) ) {
			return CoreAiState::Ready;
		}

		return [] !== CoreConnector::ai_providers() ? CoreAiState::NeedsKey : CoreAiState::NeedsProvider;
	}

	/** @return list<CoreConnector> */
	public function connectors(): array {
		return CoreConnector::ai_providers();
	}

	/**
	 * Stores the key in WordPress's own connector setting.
	 *
	 * Core hands stored keys to the AI client on the next request, so the
	 * redirect re-evaluates core_state(): the chooser then shows either the
	 * ready card or, when the key was refused, says so.
	 */
	public function handle_save_connector_key(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'counterhand-mcp-for-woocommerce' ) );
		}

		check_admin_referer( 'ctrh_save_connector_key' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$connector = CoreConnector::find( sanitize_key( wp_unslash( $_POST['ctrh_connector_id'] ?? '' ) ) );
		$key       = sanitize_text_field( wp_unslash( $_POST['ctrh_connector_key'] ?? '' ) );
		// phpcs:enable

		if ( null === $connector ) {
			$this->redirect_back( new ConnectResult( false, __( 'Unknown provider.', 'counterhand-mcp-for-woocommerce' ) ) );
		}

		if ( '' === $key ) {
			$this->redirect_back( new ConnectResult( false, __( 'Paste the API key first.', 'counterhand-mcp-for-woocommerce' ) ) );
		}

		$connector->save_key( $key );

		$this->redirect_back(
			new ConnectResult(
				true,
				sprintf(
					/* translators: %s: provider name */
					__( 'Saved your %s key.', 'counterhand-mcp-for-woocommerce' ),
					$connector->name
				)
			)
		);
	}

	/** No-JS fallback for the install buttons; the AJAX path is the primary one. */
	public function handle_install_provider(): void {
		check_admin_referer( 'ctrh_install_provider' );

		$this->redirect_back( $this->install_result() );
	}

	/** AJAX install: the button shows progress and the page confirms on reload. */
	public function handle_install_provider_ajax(): void {
		check_ajax_referer( 'ctrh_install_provider' );

		$result = $this->install_result();

		// Parked so the reload greets the user with the same notice.
		set_transient( self::RESULT_TRANSIENT . get_current_user_id(), $result->to_array(), 60 );

		if ( ! $result->ok ) {
			wp_send_json_error( $result->to_array() );
		}

		wp_send_json_success( $result->to_array() );
	}

	private function install_result(): ConnectResult {
		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
			return new ConnectResult( false, __( 'You are not allowed to install plugins.', 'counterhand-mcp-for-woocommerce' ) );
		}

		$plugin = ProviderPlugin::tryFrom(
			sanitize_key( wp_unslash( $_POST['ctrh_provider_slug'] ?? '' ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- both callers verify a nonce first.
		);

		if ( null === $plugin ) {
			return new ConnectResult( false, __( 'Unknown provider plugin.', 'counterhand-mcp-for-woocommerce' ) );
		}

		$basename = $plugin->installed_basename() ?? $this->install( $plugin );

		if ( null === $basename ) {
			return new ConnectResult( false, __( 'The provider plugin could not be installed. Try it from the Plugins screen.', 'counterhand-mcp-for-woocommerce' ) );
		}

		$activated = is_plugin_active( $basename ) ? null : activate_plugin( $basename );

		if ( is_wp_error( $activated ) ) {
			return new ConnectResult( false, $activated->get_error_message() );
		}

		return new ConnectResult(
			true,
			sprintf(
				/* translators: %s: provider plugin name */
				__( '%s is installed. Last step: add your API key where WordPress keeps it.', 'counterhand-mcp-for-woocommerce' ),
				$plugin->label()
			)
		);
	}

	private function install( ProviderPlugin $plugin ): ?string {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$api = plugins_api( 'plugin_information', [ 'slug' => $plugin->value ] );

		if ( is_wp_error( $api ) || ! isset( $api->download_link ) ) {
			return null;
		}

		$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );

		if ( true !== $upgrader->install( $api->download_link ) ) {
			return null;
		}

		$basename = $upgrader->plugin_info();

		return is_string( $basename ) ? $basename : null;
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'counterhand-mcp-for-woocommerce' ) );
		}

		check_admin_referer( 'ctrh_save_chat' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		if ( isset( $_POST['ctrh_chat_forget'] ) ) {
			$this->settings->forget_key();
			$this->redirect_back( new ConnectResult( true, __( 'The saved key was removed.', 'counterhand-mcp-for-woocommerce' ) ) );
		}

		$provider_id = sanitize_key( wp_unslash( $_POST['ctrh_chat_provider'] ?? '' ) );
		$model       = sanitize_text_field( wp_unslash( $_POST['ctrh_chat_model'] ?? '' ) );
		$base_url    = esc_url_raw( wp_unslash( $_POST['ctrh_chat_base_url'] ?? '' ) );
		$key         = sanitize_text_field( wp_unslash( $_POST['ctrh_chat_key'] ?? '' ) );
		// phpcs:enable

		$provider = $this->providers->get( $provider_id );

		if ( null === $provider ) {
			$this->redirect_back( new ConnectResult( false, __( 'Choose a model provider first.', 'counterhand-mcp-for-woocommerce' ) ) );
		}

		// Blank key field means "keep the stored one" — no retyping secrets.
		$effective_key = '' !== $key ? $key : $this->settings->api_key();

		if ( $provider->needs_key() && '' === $effective_key ) {
			$this->redirect_back( new ConnectResult( false, __( 'This provider needs an API key.', 'counterhand-mcp-for-woocommerce' ) ) );
		}

		$config = new ProviderConfig(
			api_key: $effective_key,
			model: $model,
			base_url: '' !== $base_url ? $base_url : $provider->default_base_url(),
			system_prompt: '',
		);

		try {
			$provider->test( $config );
		} catch ( ToolCallException $exception ) {
			$this->redirect_back( new ConnectResult( false, $exception->getMessage() ) );
		} catch ( \Throwable ) {
			$this->redirect_back( new ConnectResult( false, __( 'The model could not be reached. Check the details and try again.', 'counterhand-mcp-for-woocommerce' ) ) );
		}

		$this->settings->save( $provider_id, $model, $config->base_url, $key );

		$this->redirect_back(
			new ConnectResult(
				true,
				sprintf(
					/* translators: 1: provider name, 2: model name */
					__( 'Connected to %1$s%2$s.', 'counterhand-mcp-for-woocommerce' ),
					$provider->label(),
					'' !== $model ? ' · ' . $model : ''
				)
			)
		);
	}

	public function take_result(): ?ConnectResult {
		$key    = self::RESULT_TRANSIENT . get_current_user_id();
		$result = ConnectResult::from_array( get_transient( $key ) );

		if ( null !== $result ) {
			delete_transient( $key );
		}

		return $result;
	}

	// Post-redirect-get so a refresh cannot resubmit the key.
	private function redirect_back( ConnectResult $result ): never {
		set_transient( self::RESULT_TRANSIENT . get_current_user_id(), $result->to_array(), 60 );

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'counterhand-mcp' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
