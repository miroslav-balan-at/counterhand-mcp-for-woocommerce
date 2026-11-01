<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Domain\CurrencyPrices;

defined( 'ABSPATH' ) || exit;

/** WooCommerce Multilingual's multi-currency mode. */
interface MultiCurrencyInterface {

	public function is_on(): bool;

	/** @return list<string> Currency codes other than the store's own. */
	public function currencies(): array;

	public function original_product_id( int $product_id ): int;

	public function is_variable( int $product_id ): bool;

	public function has_own_prices( int $product_id ): bool;

	public function prices( int $product_id, string $currency ): CurrencyPrices;
}
