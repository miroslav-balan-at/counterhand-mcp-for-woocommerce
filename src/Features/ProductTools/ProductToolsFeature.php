<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\ProductTools;

use AgentGateMcp\Features\McpServer\ToolRegistry;
use AgentGateMcp\Shared\FeatureInterface;
use AgentGateMcp\Shared\WooCommerce\ResponseShaper;
use AgentGateMcp\Shared\WooCommerce\RestGateway;

defined( 'ABSPATH' ) || exit;

final readonly class ProductToolsFeature implements FeatureInterface {

	public function __construct(
		private ToolRegistry $tool_registry,
		private RestGateway $gateway,
		private ResponseShaper $shaper,
	) {}

	public function register(): void {
		$this->tool_registry->add( new ListProductsTool( $this->gateway, $this->shaper ) );
		$this->tool_registry->add( new GetProductTool( $this->gateway, $this->shaper ) );
		$this->tool_registry->add( new CreateProductTool( $this->gateway, $this->shaper ) );
		$this->tool_registry->add( new UpdateProductTool( $this->gateway, $this->shaper ) );
		$this->tool_registry->add( new DeleteProductTool( $this->gateway, $this->shaper ) );
	}
}
