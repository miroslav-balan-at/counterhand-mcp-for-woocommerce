<?php

declare( strict_types=1 );

namespace Counterhand;

use Counterhand\Features\ActionLog\ActionLogFeature;
use Counterhand\Features\McpServer\McpServer;
use Counterhand\Features\McpServer\McpServerFeature;
use Counterhand\Features\McpServer\ToolDispatcher;
use Counterhand\Features\McpServer\ToolRegistry;
use Counterhand\Features\Licensing\FreemiusLicence;
use Counterhand\Features\Licensing\Licence;
use Counterhand\Features\Licensing\UnlicensedFallback;
use Counterhand\Features\OAuth\OAuthFeature;
use Counterhand\Features\Playground\AgentLoop;
use Counterhand\Features\Playground\ChatSettings;
use Counterhand\Features\Playground\ModelConnect;
use Counterhand\Features\Playground\PlaygroundFeature;
use Counterhand\Features\Playground\Provider\ProviderRegistry;
use Counterhand\Features\Settings\ConnectionMatcher;
use Counterhand\Features\Settings\ConnectReadiness;
use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Settings\SettingSanitizer;
use Counterhand\Features\Settings\SettingsFeature;
use Counterhand\Features\Settings\StoreToolPolicy;
use Counterhand\Features\Tokens\Authentication\RateLimiter;
use Counterhand\Features\Tokens\Authentication\TokenAuthenticator;
use Counterhand\Features\Tokens\Persistence\Schema;
use Counterhand\Features\Tokens\Persistence\WpdbTokenRepository;
use Counterhand\Features\Tokens\TokensFeature;
use Counterhand\Features\WooCommerceTools\Application\ToolFactory;
use Counterhand\Features\WooCommerceTools\Descriptors\StaticDescriptorCatalog;
use Counterhand\Features\WooCommerceTools\Domain\MetaKeyPolicy;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestGateway;
use Counterhand\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use Counterhand\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use Counterhand\Features\WooCommerceTools\Infrastructure\SchemaProvider;
use Counterhand\Features\WooCommerceTools\WooCommerceToolsFeature;

defined( 'ABSPATH' ) || exit;

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

	public static function activate(): void {
		Schema::install();
		\Counterhand\Features\ActionLog\Persistence\LogSchema::install();
		McpServerFeature::register_rewrite();
		OAuthFeature::register_rewrites();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
		wp_clear_scheduled_hook( 'ctrh_purge_log' );
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

		// Asked once and passed down, so no feature reaches for the vendor
		// itself and a licensing fault degrades in exactly one place.
		$licence = FreemiusLicence::detect() ?? new UnlicensedFallback();

		$this->tool_registry = new ToolRegistry( $settings );

		// One pipeline shared by the HTTP endpoint and the admin playground, so
		// both dispatch through exactly the same gates — the playground just
		// skips the JSON-RPC framing.
		$tool_dispatcher = new ToolDispatcher( $this->tool_registry );
		$mcp_server      = new McpServer( $tool_dispatcher );

		$tokens     = new TokensFeature( $repository );
		$action_log = new ActionLogFeature( $settings );

		$chat_settings  = new ChatSettings();
		$chat_providers = new ProviderRegistry();
		$playground     = new PlaygroundFeature(
			$tool_dispatcher,
			new AgentLoop( $tool_dispatcher ),
			$chat_settings,
			$chat_providers,
			new ModelConnect( $chat_settings, $chat_providers ),
			new StoreToolPolicy( $settings )
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
			new McpServerFeature( $settings, $authenticator, $mcp_server, $licence ),
			new OAuthFeature( $settings, $repository ),
			new WooCommerceToolsFeature( $this->tool_registry, $tool_factory, new StaticDescriptorCatalog(), $schema_provider ),
		];

		foreach ( $this->features as $feature ) {
			$feature->register();
		}
	}
}
