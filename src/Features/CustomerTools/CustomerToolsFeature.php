<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\CustomerTools;

use AgentGateMcp\Features\McpServer\ToolRegistry;
use AgentGateMcp\Shared\FeatureInterface;
use AgentGateMcp\Shared\WooCommerce\ResponseShaper;
use AgentGateMcp\Shared\WooCommerce\RestGateway;

defined( 'ABSPATH' ) || exit;

final readonly class CustomerToolsFeature implements FeatureInterface {

	public function __construct(
		private ToolRegistry $tool_registry,
		private RestGateway $gateway,
		private ResponseShaper $shaper,
	) {}

	public function register(): void {
		$this->tool_registry->add( new ListCustomersTool( $this->gateway, $this->shaper ) );
		$this->tool_registry->add( new GetCustomerTool( $this->gateway, $this->shaper ) );
	}
}
