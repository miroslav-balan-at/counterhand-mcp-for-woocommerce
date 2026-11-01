<?php

declare( strict_types=1 );

namespace Counterhand\Features\CostOfGoods;

defined( 'ABSPATH' ) || exit;

enum CostOperation: string {
	case GetProduct = 'get_product_cost';
	case SetProduct = 'set_product_cost';
	case GetOrder   = 'get_order_cost';
}
