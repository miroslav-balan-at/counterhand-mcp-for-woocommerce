<?php

declare( strict_types=1 );

namespace Counterhand\Features\CostOfGoods;

use Counterhand\Features\Tokens\Domain\IntegrationCopyInterface;

defined( 'ABSPATH' ) || exit;

final readonly class CostOfGoodsCopy implements IntegrationCopyInterface {

	public function name(): string {
		return 'WooCommerce Cost of Goods';
	}

	public function description(): string {
		return __( 'What products cost you, and what an order earned. Customers never see these.', 'counterhand-mcp-for-woocommerce' );
	}

	public function read_description(): string {
		return __( 'See the cost and price of any product, and the items, totals and costs of any order, including drafts. No customer details are included.', 'counterhand-mcp-for-woocommerce' );
	}

	public function write_description(): string {
		return __( 'Set what a product costs. This cannot change its price, and orders already placed keep the cost they were placed with.', 'counterhand-mcp-for-woocommerce' );
	}
}
