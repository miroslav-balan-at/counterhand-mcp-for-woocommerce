<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Features\Licensing\Licence;
use Counterhand\Features\McpServer\ToolDispatcherInterface;
use Counterhand\Features\Playground\Provider\ProviderConfig;
use Counterhand\Features\Playground\Provider\ProviderRegistry;
use Counterhand\Features\Settings\AdminScreen;
use Counterhand\Features\Settings\SettingsTabInterface;
use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Tokens\Domain\ApiToken;
use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
use Counterhand\Features\Tokens\Domain\TokenId;
use Counterhand\Features\Tokens\Domain\TokenStatus;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\FeatureInterface;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * The chat playground: talk to the store in plain language from wp-admin.
 *
 * The model runs server-side through AgentLoop, calling the same MCP tools an
 * external assistant would. No API token is involved — the caller is already an
 * authenticated administrator, so we build a synthetic in-memory agent scoped
 * to their own capabilities.
 */
final readonly class PlaygroundFeature implements FeatureInterface, SettingsTabInterface {

	private const NONCE       = 'counterhand_chat_send';
	private const TOOLS_NONCE = 'counterhand_save_chat_tools';

	public function __construct(
		private ToolDispatcherInterface $tools,
		private AgentLoop $loop,
		private ChatSettings $settings,
		private ProviderRegistry $providers,
		private ModelConnect $model_connect,
		private ChatToolPolicy $store_policy,
		private Licence $licence,
	) {}

	public function register(): void {
		// The chat is the whole product with a friendlier front door: it runs
		// every tool an external assistant could. Leaving it ungated gave the
		// plugin away and left the licence guarding only the harder-to-use
		// half. The trial is what lets someone evaluate it — and
		// can_use_premium_code() is true throughout one, so no branch here
		// needs to know about trials.
		if ( ! $this->licence->is_active() ) {
			return;
		}

		add_action( 'wp_ajax_counterhand_chat_send', [ $this, 'handle_send' ] );
		add_action( 'admin_post_' . self::TOOLS_NONCE, [ $this, 'handle_save_tools' ] );
		$this->model_connect->register();
	}

	/**
	 * Shown in place of the chat when there is no licence or trial.
	 *
	 * Says what the plugin does and where to start, rather than only that
	 * something is locked: a store owner who has just installed this has no
	 * other way to find out.
	 */
	private function render_trial_invitation(): void {
		?>
		<div class="counterhand-notice counterhand-notice--info">
			<h2><?php esc_html_e( 'Start your free trial', 'counterhand-mcp-for-woocommerce' ); ?></h2>
			<p>
				<?php esc_html_e( 'Counterhand lets you ask about sales, look up orders and update products in plain language — here in your admin, or from Claude and ChatGPT. You choose what each assistant may reach, and you can revoke it at any time.', 'counterhand-mcp-for-woocommerce' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $this->licence->upgrade_url() ); ?>">
					<?php esc_html_e( 'Start the trial', 'counterhand-mcp-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/** Asks the provider itself — readiness is provider knowledge, not a flag. */
	public function is_ready(): bool {
		$provider = $this->providers->get( $this->settings->provider_id() );

		return null !== $provider && $provider->is_ready( $this->config() );
	}

	public function render_tab(): void {
		// register() has already withheld the handlers, so without this the tab
		// would render a chat box that silently does nothing.
		if ( ! $this->licence->is_active() ) {
			$this->render_trial_invitation();

			return;
		}

		// "Change" in the chat footer reopens the chooser without disconnecting
		// anything, so switching models never means leaving the tab.
		$changing = isset( $_GET['counterhand_change_model'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- toggles a read-only view.

		$tools              = $this->tools->visible_for( $this->synthetic_agent() );
		$chat_groups        = $this->settings->groups();
		$tool_sections      = ToolSection::populated();
		$chat_areas         = $this->areas( $chat_groups );
		$store_settings_url = $this->store_policy->settings_url();
		// Null when the connected model can search a deferred catalogue, in
		// which case there is no count for the panel to warn about.
		$tool_limit         = $this->providers->get( $this->settings->provider_id() )?->max_eager_tools();
		$is_ready           = ! $changing && $this->is_ready();
		$send_nonce         = wp_create_nonce( self::NONCE );
		$chat_settings      = $this->settings;
		$chat_providers     = $this->providers->all();
		$chat_own_providers = $this->providers->user_configured();
		$active_id          = '' !== $this->settings->provider_id() ? $this->settings->provider_id() : $this->providers->default_id();
		$save_result        = $this->model_connect->take_result();
		$core_state         = $this->model_connect->core_state();
		$core_connectors    = $this->model_connect->connectors();

		include __DIR__ . '/views/tab-chat.php';
	}

	public function handle_send(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Not allowed.', 'counterhand-mcp-for-woocommerce' ) ], 403 );
		}

		check_ajax_referer( self::NONCE );

		$provider = $this->providers->get( $this->settings->provider_id() );
		if ( null === $provider ) {
			wp_send_json_error( [ 'message' => __( 'No model is connected yet. Pick one at the top of this tab.', 'counterhand-mcp-for-woocommerce' ) ] );
		}

		$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		if ( '' === trim( $message ) ) {
			wp_send_json_error( [ 'message' => __( 'Type a message first.', 'counterhand-mcp-for-woocommerce' ) ] );
		}

		$history = json_decode( wp_unslash( $_POST['history'] ?? '[]' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- provider-format JSON validated below, never output as HTML.
		if ( ! is_array( $history ) ) {
			$history = [];
		}

		try {
			$result = $this->loop->run( $provider, $this->config(), $history, $message, $this->synthetic_agent() );
		} catch ( ToolCallException $exception ) {
			wp_send_json_error( [ 'message' => $exception->getMessage() ] );
		} catch ( \Throwable $throwable ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( 'Chat request failed: ' . $throwable->getMessage(), [ 'source' => 'counterhand-mcp' ] );
			}

			wp_send_json_error( [ 'message' => __( 'The chat request failed unexpectedly. Check the WooCommerce logs for details.', 'counterhand-mcp-for-woocommerce' ) ] );
		}

		wp_send_json_success(
			[
				'transcript' => $result->transcript,
				'history'    => $result->messages,
				'usage'      => $result->usage->to_array(),
			]
		);
	}

	public function handle_save_tools(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'counterhand-mcp-for-woocommerce' ) );
		}

		check_admin_referer( self::TOOLS_NONCE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$posted = map_deep( wp_unslash( $_POST['counterhand_chat_groups'] ?? [] ), 'sanitize_key' );

		// Unticking every box posts nothing at all, which is a real choice and
		// saved as one — save_groups() drops anything that is not a live group.
		$this->settings->save_groups( is_array( $posted ) ? array_values( $posted ) : [] );

		// Post-redirect-get, so a refresh cannot resubmit the selection.
		wp_safe_redirect( AdminScreen::Chat->url() );
		exit;
	}

	/**
	 * Every area with both facts the picker needs: whether chat wants it, and
	 * whether the store exposes it at all.
	 *
	 * @param  list<ToolGroup> $selected
	 * @return array<string, ChatArea>
	 */
	private function areas( array $selected ): array {
		$counts = $this->tools->tool_counts_by_group();
		$areas  = [];

		foreach ( ToolGroup::cases() as $group ) {
			$areas[ $group->value ] = ChatArea::of(
				$group,
				$selected,
				$this->store_policy,
				$counts[ $group->value ] ?? 0
			);
		}

		return $areas;
	}

	private function config(): ProviderConfig {
		return new ProviderConfig(
			api_key: $this->settings->api_key(),
			model: $this->settings->model(),
			base_url: $this->settings->base_url(),
			system_prompt: $this->system_prompt(),
		);
	}

	/**
	 * The prompt names the areas the chat can reach, not the tools.
	 *
	 * Listing tool names here used to duplicate the tool list the provider is
	 * already sent in the same request — paid for twice, and wrong the moment a
	 * group toggle changes mid-conversation. Areas are what the model cannot
	 * infer from the tool list and what it needs to answer "can you do X?"
	 * honestly.
	 */
	private function system_prompt(): string {
		$areas = array_map(
			static fn ( ToolGroup $group ): string => $group->noun(),
			$this->settings->groups()
		);

		return sprintf(
			/* translators: 1: store name, 2: currency code, 3: comma-separated areas of the store, e.g. "products, orders" */
			__( 'You are a WooCommerce store assistant for "%1$s". Prices are in %2$s. You can reach these areas of the store: %3$s. Use the available tools to answer questions and make changes — never guess at store data you can look up, and say plainly when something is outside what you can reach. New products are created as drafts for the administrator to review. Confirm before any destructive action. Answer concisely and mention the concrete records you touched.', 'counterhand-mcp-for-woocommerce' ),
			get_bloginfo( 'name' ),
			function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR',
			[] !== $areas ? implode( ', ', $areas ) : __( 'none — no tool groups are selected for chat', 'counterhand-mcp-for-woocommerce' )
		);
	}

	/**
	 * An in-memory agent representing the current administrator, scoped to the
	 * groups the Chat tab has selected.
	 *
	 * It used to hold the entire scope catalogue on the reasoning that an admin
	 * already holds manage_woocommerce. True, but beside the point: the cost of a
	 * broad grant here is not privilege, it is that every enabled group's schemas
	 * ride along on every message. The store's own group toggles still apply on
	 * top of this, so the chat can never reach further than the connector does.
	 */
	private function synthetic_agent(): AuthenticatedAgent {
		$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

		return new AuthenticatedAgent(
			new ApiToken(
				id: 0,
				token_id: TokenId::try_from_string( 'playground000000' ) ?? throw new \RuntimeException( 'Invalid playground token id.' ),
				label: __( 'Admin chat', 'counterhand-mcp-for-woocommerce' ),
				scopes: GrantedScopeSet::from_values( $this->chat_scopes() ),
				status: TokenStatus::Active,
				owner_user_id: get_current_user_id(),
				created_at: $now,
				last_used_at: null,
				expires_at: null,
			)
		);
	}

	/**
	 * Both axes of every selected group. Narrowing further is the store's job,
	 * not this one's — a group's write toggle already decides that.
	 *
	 * @return list<string>
	 */
	private function chat_scopes(): array {
		$scopes = [];

		foreach ( $this->settings->groups() as $group ) {
			$scopes[] = $group->read_scope()->value;

			$write = $group->write_scope();
			if ( null !== $write ) {
				$scopes[] = $write->value;
			}
		}

		return $scopes;
	}
}
