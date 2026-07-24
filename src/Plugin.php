<?php

declare( strict_types=1 );

namespace AgentGateMcp;

use AgentGateMcp\Features\CustomerTools\CustomerToolsFeature;
use AgentGateMcp\Features\McpServer\McpServerFeature;
use AgentGateMcp\Features\McpServer\ToolRegistry;
use AgentGateMcp\Features\OrderTools\OrderToolsFeature;
use AgentGateMcp\Features\ProductTools\ProductToolsFeature;
use AgentGateMcp\Features\ReportTools\ReportToolsFeature;
use AgentGateMcp\Features\Settings\PluginSettings;
use AgentGateMcp\Features\Tokens\Authentication\RateLimiter;
use AgentGateMcp\Features\Tokens\Authentication\TokenAuthenticator;
use AgentGateMcp\Features\Tokens\Persistence\Schema;
use AgentGateMcp\Features\Tokens\Persistence\WpdbTokenRepository;
use AgentGateMcp\Features\Tokens\TokensFeature;
use AgentGateMcp\Shared\WooCommerce\ResponseShaper;
use AgentGateMcp\Shared\WooCommerce\RestGateway;

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
		McpServerFeature::register_rewrite();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public function tool_registry(): ?ToolRegistry {
		return $this->tool_registry;
	}

	private function register_features(): void {
		$settings      = new PluginSettings();
		$repository    = new WpdbTokenRepository();
		$authenticator = new TokenAuthenticator( $repository, new RateLimiter( $settings ) );
		$gateway       = new RestGateway();
		$shaper        = new ResponseShaper();

		$this->tool_registry = new ToolRegistry( $settings );

		$this->features = [
			new TokensFeature( $repository ),
			new McpServerFeature( $settings, $authenticator, $this->tool_registry ),
			new ProductToolsFeature( $this->tool_registry, $gateway, $shaper ),
			new OrderToolsFeature( $this->tool_registry, $gateway, $shaper ),
			new CustomerToolsFeature( $this->tool_registry, $gateway, $shaper ),
			new ReportToolsFeature( $this->tool_registry, $gateway, $shaper ),
		];

		foreach ( $this->features as $feature ) {
			$feature->register();
		}
	}
}
