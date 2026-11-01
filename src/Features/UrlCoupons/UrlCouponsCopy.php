<?php

declare( strict_types=1 );

namespace Counterhand\Features\UrlCoupons;

use Counterhand\Features\Tokens\Domain\IntegrationCopyInterface;

defined( 'ABSPATH' ) || exit;

final readonly class UrlCouponsCopy implements IntegrationCopyInterface {

	public function name(): string {
		return 'WooCommerce URL Coupons';
	}

	public function description(): string {
		return __( 'Links that apply a coupon to whoever opens them.', 'counterhand-mcp-for-woocommerce' );
	}

	public function read_description(): string {
		return __( 'See which coupons have a link, and where it leads.', 'counterhand-mcp-for-woocommerce' );
	}

	public function write_description(): string {
		return __( 'Give any existing coupon a link or remove one. Anyone with the link gets the discount, so every change needs explicit confirmation. This cannot create a coupon or change its discount.', 'counterhand-mcp-for-woocommerce' );
	}
}
