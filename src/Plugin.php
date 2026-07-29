<?php

declare( strict_types=1 );

namespace AgentGateMcp;

use AgentGateMcp\Features\ActionLog\ActionLogFeature;
use AgentGateMcp\Features\McpServer\McpServer;
use AgentGateMcp\Features\McpServer\McpServerFeature;
use AgentGateMcp\Features\McpServer\ToolRegistry;
use AgentGateMcp\Features\OAuth\OAuthFeature;
use AgentGateMcp\Features\Playground\AgentLoop;
use AgentGateMcp\Features\Playground\ChatSettings;
use AgentGateMcp\Features\Playground\ModelConnect;
use AgentGateMcp\Features\Playground\PlaygroundFeature;
use AgentGateMcp\Features\Playground\Provider\ProviderRegistry;
use AgentGateMcp\Features\Settings\ConnectionMatcher;
use AgentGateMcp\Features\Settings\ConnectReadiness;
use AgentGateMcp\Features\Settings\PluginSettings;
use AgentGateMcp\Features\Settings\SettingSanitizer;
use AgentGateMcp\Features\Settings\SettingsFeature;
use AgentGateMcp\Features\Tokens\Authentication\RateLimiter;
use AgentGateMcp\Features\Tokens\Authentication\TokenAuthenticator;
use AgentGateMcp\Features\Tokens\Persistence\Schema;
use AgentGateMcp\Features\Tokens\Persistence\WpdbTokenRepository;
use AgentGateMcp\Features\Tokens\TokensFeature;
use AgentGateMcp\Features\WooCommerceTools\Application\ToolFactory;
use AgentGateMcp\Features\WooCommerceTools\Descriptors\StaticDescriptorCatalog;
use AgentGateMcp\Features\WooCommerceTools\Domain\MetaKeyPolicy;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestGateway;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\SchemaProvider;
use AgentGateMcp\Features\WooCommerceTools\WooCommerceToolsFeature;

defined( 'ABSPATH' ) || exit;

/**
 * Composition root: wires shared services and boots every feature slice.
 */
final class Plugin {

	private static ?self $instance = null;

	/** @var list<\AgentGateMcp\Shared\FeatureInterface> */
	private array $features = [];

	private ?ToolRegistry $tool_registry = null;

	public static function boot(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register_features();
		}

		return self::$instance;
	}

	public static function activate(): void {
		Schema::install();
		\AgentGateMcp\Features\ActionLog\Persistence\LogSchema::install();
		McpServerFeature::register_rewrite();
		OAuthFeature::register_rewrites();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
		wp_clear_scheduled_hook( 'agmcp_purge_log' );
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

		// One protocol instance shared by the HTTP endpoint and the admin
		// playground, so both dispatch through exactly the same path.
		$mcp_server = new McpServer( $this->tool_registry );

		$tokens     = new TokensFeature( $repository );
		$action_log = new ActionLogFeature( $settings );

		$chat_settings  = new ChatSettings();
		$chat_providers = new ProviderRegistry();
		$playground     = new PlaygroundFeature(
			$this->tool_registry,
			new AgentLoop( $this->tool_registry, $mcp_server ),
			$chat_settings,
			$chat_providers,
			new ModelConnect( $chat_settings, $chat_providers )
		);

		$this->features = [
			$tokens,
			$action_log,
			$playground,
			new SettingsFeature(
				$settings,
				$tokens->admin(),
				$action_log,
				$playground,
				new ConnectReadiness(),
				new ConnectionMatcher( $repository ),
				new SettingSanitizer()
			),
			new McpServerFeature( $settings, $authenticator, $mcp_server ),
			new OAuthFeature( $settings, $repository ),
			new WooCommerceToolsFeature( $this->tool_registry, $tool_factory, new StaticDescriptorCatalog(), $schema_provider ),
		];

		foreach ( $this->features as $feature ) {
			$feature->register();
		}
	}
}
