<?php

declare( strict_types=1 );

namespace Counterhand;

// Plugin Check reports this guard as missing when it sits below this file's imports.
defined( 'ABSPATH' ) || exit;

use Counterhand\Features\Abilities\AbilitiesFeature;
use Counterhand\Features\Abilities\AbilityCatalog;
use Counterhand\Features\ActionLog\ActionLogFeature;
use Counterhand\Features\ActionLog\Persistence\WpdbActivityReport;
use Counterhand\Features\McpApps\AppViewCatalog;
use Counterhand\Features\CostOfGoods\CostOfGoodsFeature;
use Counterhand\Features\CostOfGoods\WooCommerceCostLedger;
use Counterhand\Features\Flamingo\FlamingoFeature;
use Counterhand\Features\Flamingo\FlamingoInbox;
use Counterhand\Features\McpServer\ConfirmationState;
use Counterhand\Features\McpServer\McpServer;
use Counterhand\Features\McpServer\McpServerFeature;
use Counterhand\Features\McpServer\ToolDispatcher;
use Counterhand\Features\McpServer\ToolRegistry;
use Counterhand\Features\OAuth\OAuthFeature;
use Counterhand\Features\Playground\AgentLoop;
use Counterhand\Features\Playground\ChatNotesFeature;
use Counterhand\Features\Playground\ChatSettings;
use Counterhand\Features\Playground\ConnectedTextModel;
use Counterhand\Features\Playground\ModelConnect;
use Counterhand\Features\Playground\PendingConfirmationStore;
use Counterhand\Features\Playground\PlaygroundFeature;
use Counterhand\Features\Playground\SavedPrompts;
use Counterhand\Features\Playground\StoreMemory;
use Counterhand\Features\Playbooks\PlaybookCatalog;
use Counterhand\Features\Playground\Rewrites\Persistence\RewriteSchema;
use Counterhand\Features\Playground\Rewrites\RewriteJobs;
use Counterhand\Features\Playground\Rewrites\RewritesFeature;
use Counterhand\Features\Playground\Rewrites\WpdbRewriteQueue;
use Counterhand\Features\Playground\Provider\ProviderRegistry;
use Counterhand\Features\Privacy\PrivacyFeature;
use Counterhand\Features\Settings\AdminScreen;
use Counterhand\Features\Settings\ConnectionMatcher;
use Counterhand\Features\Settings\ConnectReadiness;
use Counterhand\Features\Settings\ConnectScreen;
use Counterhand\Features\Settings\DashboardScreen;
use Counterhand\Features\Settings\IntegrationsScreen;
use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Settings\SettingSanitizer;
use Counterhand\Features\Settings\SettingsFeature;
use Counterhand\Features\Settings\SettingsScreen;
use Counterhand\Features\Settings\StoreToolPolicy;
use Counterhand\Features\Tokens\Authentication\RateLimiter;
use Counterhand\Features\Tokens\Authentication\TokenAuthenticator;
use Counterhand\Features\Tokens\Persistence\Schema;
use Counterhand\Features\Tokens\Persistence\WpdbTokenRepository;
use Counterhand\Features\Tokens\TokensFeature;
use Counterhand\Features\Undo\Persistence\ChangeSchema;
use Counterhand\Features\Undo\UndoFeature;
use Counterhand\Features\Undo\WpdbChangeJournal;
use Counterhand\Features\UrlCoupons\UrlCouponsFeature;
use Counterhand\Features\WeeklyReport\FigureReader;
use Counterhand\Features\WeeklyReport\ReportSummary;
use Counterhand\Features\WeeklyReport\Subscriptions;
use Counterhand\Features\WeeklyReport\WeeklyReport;
use Counterhand\Features\WeeklyReport\WeeklyReportFeature;
use Counterhand\Features\WooCommerceTools\Application\ServedIntegrationGroups;
use Counterhand\Features\WooCommerceTools\Application\StoreProfileResource;
use Counterhand\Features\WooCommerceTools\Application\ToolFactory;
use Counterhand\Features\WooCommerceTools\Descriptors\StaticDescriptorCatalog;
use Counterhand\Features\WooCommerceTools\Domain\MetaKeyPolicy;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestGateway;
use Counterhand\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use Counterhand\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use Counterhand\Features\WooCommerceTools\Infrastructure\SchemaProvider;
use Counterhand\Features\WooCommerceTools\WooCommerceToolsFeature;

/**
 * Composition root: wires shared services and boots every feature slice.
 */
final class Plugin {

	private static ?self $instance = null;

	/** @var list<\Counterhand\Shared\FeatureInterface> */
	private array $features = [];

	private ?ToolRegistry $tool_registry = null;

	public static function boot(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register_features();
		}

		return self::$instance;
	}

	/** A network activation reaches every existing site. A site added later installs itself on first use. */
	public static function activate( bool $network_wide = false ): void {
		if ( ! $network_wide || ! is_multisite() ) {
			self::activate_site();

			return;
		}

		foreach ( get_sites(
			[
				'fields' => 'ids',
				'number' => 0,
			]
		) as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::activate_site();
			restore_current_blog();
		}
	}

	private static function activate_site(): void {
		Schema::install();
		\Counterhand\Features\ActionLog\Persistence\LogSchema::install();
		ChangeSchema::install();
		RewriteSchema::install();
		McpServerFeature::register_rewrite();
		OAuthFeature::register_rewrites();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
		wp_clear_scheduled_hook( 'counterhand_purge_log' );
		wp_clear_scheduled_hook( UndoFeature::PURGE_HOOK );
		wp_clear_scheduled_hook( ConfirmationState::PURGE_HOOK );
		wp_clear_scheduled_hook( RewriteJobs::PURGE_HOOK );
		wp_clear_scheduled_hook( WeeklyReport::HOOK );

		// Left queued, each would fail later for want of its callback.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( RewriteJobs::HOOK );
		}
	}

	public function tool_registry(): ?ToolRegistry {
		return $this->tool_registry;
	}

	private function register_features(): void {
		$settings      = new PluginSettings();
		$repository    = new WpdbTokenRepository();
		$authenticator = new TokenAuthenticator( $repository, new RateLimiter( $settings ) );
		$gateway       = new RestGateway();

		// One catalog and one probe for the whole request: the route table is
		// walked once, and each permission callback is asked once.
		$route_catalog = new RouteCatalog();

		// One schema provider for the request: describe_woocommerce_fields and
		// the generated tools then share a single derivation memo.
		$schema_provider = new SchemaProvider( $route_catalog );

		global $wpdb;

		$tool_factory = new ToolFactory(
			$gateway,
			$route_catalog,
			new RoutePermissionProbe( $route_catalog ),
			$schema_provider,
			// The capabilities key is $wpdb->prefix . 'capabilities', and the
			// prefix is per-install — a hardcoded wp_ would protect nothing on
			// a store that uses its own.
			new MetaKeyPolicy( $wpdb->prefix )
		);

		$this->tool_registry = new ToolRegistry( $settings );

		// One pipeline shared by the HTTP endpoint and the admin playground, so
		// both dispatch through exactly the same gates — the playground just
		// skips the JSON-RPC framing.
		$change_journal  = new WpdbChangeJournal();
		$tool_dispatcher = new ToolDispatcher( $this->tool_registry, $change_journal );
		$playbooks       = new PlaybookCatalog( $tool_dispatcher );
		$mcp_server      = new McpServer( $tool_dispatcher, $playbooks, new StoreProfileResource( $tool_dispatcher ), views: new AppViewCatalog( $tool_dispatcher ) );

		$tokens     = new TokensFeature( $repository );
		$action_log = new ActionLogFeature( $settings );

		$chat_settings  = new ChatSettings();
		$store_memory   = new StoreMemory();
		$saved_prompts  = new SavedPrompts();
		$chat_providers = new ProviderRegistry();
		$pending_store  = new PendingConfirmationStore();
		$playground     = new PlaygroundFeature(
			$tool_dispatcher,
			new AgentLoop( $tool_dispatcher, $pending_store ),
			$pending_store,
			$chat_settings,
			$chat_providers,
			new ModelConnect( $chat_settings, $chat_providers ),
			new StoreToolPolicy( $settings ),
			$playbooks,
			$store_memory,
			$saved_prompts
		);

		$weekly_report = new WeeklyReportFeature(
			new WeeklyReport(
				new FigureReader( $tool_dispatcher ),
				new ReportSummary( new ConnectedTextModel( $chat_settings, $chat_providers ) ),
				new Subscriptions()
			)
		);

		$rewrites = new RewritesFeature( new RewriteJobs( $tool_dispatcher, new WpdbRewriteQueue(), $chat_settings, $chat_providers ) );

		$descriptors        = new StaticDescriptorCatalog();
		$connection_matcher = new ConnectionMatcher( $repository );

		$this->features = [
			$tokens,
			$action_log,
			$playground,
			new ChatNotesFeature( $store_memory, $saved_prompts ),
			$rewrites,
			$weekly_report,
			new SettingsFeature(
				[
					AdminScreen::Chat->value         => $playground,
					AdminScreen::Rewrites->value     => $rewrites,
					AdminScreen::Dashboard->value    => new DashboardScreen( $settings, $connection_matcher, new WpdbActivityReport(), [ $weekly_report ] ),
					AdminScreen::Connect->value      => new ConnectScreen( $tokens->admin(), $connection_matcher ),
					AdminScreen::Integrations->value => new IntegrationsScreen( $settings ),
					AdminScreen::Settings->value     => new SettingsScreen( $settings ),
					AdminScreen::Log->value          => $action_log,
				],
				new ConnectReadiness(),
				$connection_matcher,
				new SettingSanitizer(),
				$settings
			),
			new McpServerFeature( $settings, $authenticator, $mcp_server ),
			new OAuthFeature( $settings, $repository, $this->tool_registry ),
			new WooCommerceToolsFeature( $this->tool_registry, $tool_factory, $descriptors, $schema_provider, new ServedIntegrationGroups( $descriptors, $route_catalog ) ),
			new FlamingoFeature( $this->tool_registry, new FlamingoInbox() ),
			new CostOfGoodsFeature( $this->tool_registry, new WooCommerceCostLedger() ),
			new UrlCouponsFeature( $this->tool_registry, $gateway ),
			new UndoFeature( $this->tool_registry, $tool_dispatcher, $change_journal ),
			new PrivacyFeature( $change_journal ),
			new AbilitiesFeature( $this->tool_registry, new AbilityCatalog() ),
		];

		foreach ( $this->features as $feature ) {
			$feature->register();
		}
	}
}
