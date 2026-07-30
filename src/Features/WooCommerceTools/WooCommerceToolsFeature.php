<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools;

use Counterhand\Features\McpServer\ToolRegistry;
use Counterhand\Features\WooCommerceTools\Application\DescribeFieldsTool;
use Counterhand\Features\WooCommerceTools\Application\StoreOverviewTool;
use Counterhand\Features\WooCommerceTools\Application\ToolFactory;
use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Infrastructure\SchemaProvider;
use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registers every tool derived from a wc/v3 resource descriptor.
 *
 * Registration is eager and derivation is lazy, which is the only arrangement
 * that works: the registry has to know the full surface before the first
 * request is served, while the schemas cannot be built until WooCommerce has
 * registered its controllers on init.
 */
final readonly class WooCommerceToolsFeature implements FeatureInterface {

	public function __construct(
		private ToolRegistry $tool_registry,
		private ToolFactory $factory,
		private DescriptorProvider $descriptors,
		private SchemaProvider $schemas,
	) {}

	public function register(): void {
		foreach ( $this->factory->tools( $this->descriptors ) as $tool ) {
			$this->tool_registry->add( $tool );
		}

		// The one tool with no route behind it, so no descriptor can describe it.
		$this->tool_registry->add( new StoreOverviewTool() );

		// The escape hatch from the field pruning every generated tool applies.
		$this->tool_registry->add( new DescribeFieldsTool( $this->descriptors, $this->schemas ) );
	}
}
