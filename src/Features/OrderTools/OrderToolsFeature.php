<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OrderTools;

use AgentGateMcp\Features\McpServer\ToolRegistry;
use AgentGateMcp\Shared\FeatureInterface;
use AgentGateMcp\Shared\WooCommerce\ResponseShaper;
use AgentGateMcp\Shared\WooCommerce\RestGateway;

defined( 'ABSPATH' ) || exit;

final readonly class OrderToolsFeature implements FeatureInterface {

	public function __construct(
		private ToolRegistry $tool_registry,
		private RestGateway $gateway,
		private ResponseShaper $shaper,
	) {}

	public function register(): void {
		$this->tool_registry->add( new ListOrdersTool( $this->gateway, $this->shaper ) );
		$this->tool_registry->add( new GetOrderTool( $this->gateway, $this->shaper ) );
		$this->tool_registry->add( new UpdateOrderStatusTool( $this->gateway, $this->shaper ) );
		$this->tool_registry->add( new AddOrderNoteTool( $this->gateway, $this->shaper ) );
	}
}
