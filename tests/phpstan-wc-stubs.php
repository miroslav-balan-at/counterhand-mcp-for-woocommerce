<?php
/**
 * Minimal WooCommerce symbol declarations for PHPStan.
 * Only the functions this plugin calls — not a full stub set.
 */

declare( strict_types=1 );

/** @return array<string, string> */
function wc_get_order_statuses(): array {}

function wc_orders_count( string $status ): int {}

function get_woocommerce_currency(): string {}

/** @return object{countries: object} */
function WC(): object {}

function wc_get_logger(): object {}

function wc_attribute_taxonomy_name_by_id( int $attribute_id ): string {
	return '';
}

function wc_rest_check_post_permissions( string $post_type, string $context = 'read', int $object_id = 0 ): bool {
	return false;
}

function wc_get_coupon_code_by_id( int $id ): string {
	return '';
}

function wc_url_coupons(): object {
	return new stdClass();
}

/** @return WC_Product|false|null */
function wc_get_product( int $id ) {}

/** @return WC_Order|false */
function wc_get_order( int $id ) {}

function wc_format_decimal( string $number ): string {
	return $number;
}

class WC_Product {
	public function get_name(): string {
		return '';
	}

	public function get_type(): string {
		return '';
	}

	/** @return string|float */
	public function get_price( string $context = 'view' ) {
		return '';
	}

	public function update_meta_data( string $key, mixed $value ): void {}

	public function save(): int {
		return 0;
	}
}

class WC_Order_Item {
	public function get_name(): string {
		return '';
	}

	public function get_quantity(): int {
		return 0;
	}
}

class WC_Order_Item_Product extends WC_Order_Item {
	public function get_product_id(): int {
		return 0;
	}

	/** @return string|float */
	public function get_total() {
		return '';
	}

	public function get_meta( string $key ): mixed {
		return '';
	}
}

class WC_Order_Refund {
	/** @return list<WC_Order_Item> */
	public function get_items(): array {
		return [];
	}

	/** @return string|float */
	public function get_total() {
		return '';
	}
}

class WC_Order {
	public function get_status(): string {
		return '';
	}

	/** @return list<WC_Order_Refund> */
	public function get_refunds(): array {
		return [];
	}

	/** @return list<WC_Order_Item> */
	public function get_items(): array {
		return [];
	}

	/** @return string|float */
	public function get_total() {
		return '';
	}

	public function get_currency(): string {
		return '';
	}
}

/** @return list<string> */
function wc_get_is_paid_statuses(): array {
	return [];
}

class WC_Shipping_Zones {
	/** @return array<int, array<string, mixed>> */
	public static function get_zones(): array {
		return [];
	}
}

/** @param array<mixed> $args */
function as_enqueue_async_action( string $hook, array $args = [], string $group = '', bool $unique = false, int $priority = 10 ): int {}

/** @param array<mixed> $args */
function as_unschedule_all_actions( string $hook, array $args = [], string $group = '' ): void {}
