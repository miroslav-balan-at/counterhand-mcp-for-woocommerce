<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\Tokens\Domain\IntegrationCopyInterface;

defined( 'ABSPATH' ) || exit;

final readonly class ShipmentTrackingCopy implements IntegrationCopyInterface {

	public function name(): string {
		return 'WooCommerce Shipment Tracking';
	}

	public function description(): string {
		return __( 'Tracking numbers on orders, which customers see in their emails and account.', 'counterhand-mcp-for-woocommerce' );
	}

	public function read_description(): string {
		return __( 'See the tracking numbers on any order, by order id. Nothing else about the order is included.', 'counterhand-mcp-for-woocommerce' );
	}

	public function write_description(): string {
		return __( 'Add and remove tracking numbers on any order. Customers see them, including any custom tracking link, so every change needs explicit confirmation.', 'counterhand-mcp-for-woocommerce' );
	}
}
