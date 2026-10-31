<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\Tokens\Domain\IntegrationCopyInterface;

defined( 'ABSPATH' ) || exit;

final readonly class AcfCopy implements IntegrationCopyInterface {

	public function name(): string {
		return 'Advanced Custom Fields';
	}

	public function description(): string {
		return __( 'Custom field values on posts, pages and products, for field groups with "Show in REST API" switched on.', 'counterhand-mcp-for-woocommerce' );
	}

	public function read_description(): string {
		return __( 'See custom field values on posts, pages and products, for field groups with "Show in REST API" switched on. Password fields are never returned.', 'counterhand-mcp-for-woocommerce' );
	}

	public function write_description(): string {
		return __( 'Change those custom field values. Password fields and fields stored under a protected name cannot be changed.', 'counterhand-mcp-for-woocommerce' );
	}
}
