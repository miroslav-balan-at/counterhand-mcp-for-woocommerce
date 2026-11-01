<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Domain\CurrencyPrices;

defined( 'ABSPATH' ) || exit;

final readonly class WooCommerceMultilingualCurrency implements MultiCurrencyInterface {

	/** Multilingual stores a product's own prices by currency, on the original only. */
	public function is_on(): bool {
		return function_exists( 'wpml_element_language_details_filter' )
			&& function_exists( 'wcml_is_multi_currency_on' )
			&& (bool) wcml_is_multi_currency_on();
	}

	public function currencies(): array {
		global $woocommerce_wpml;

		return array_values( array_map( 'strval', array_keys( (array) $woocommerce_wpml->multi_currency->get_currencies() ) ) );
	}

	public function original_product_id( int $product_id ): int {
		global $woocommerce_wpml;

		return (int) $woocommerce_wpml->products->get_original_product_id( $product_id );
	}

	public function is_variable( int $product_id ): bool {
		global $woocommerce_wpml;

		return (bool) $woocommerce_wpml->products->is_variable_product( $product_id );
	}

	public function has_own_prices( int $product_id ): bool {
		return 1 === (int) get_post_meta( $product_id, '_wcml_custom_prices_status', true );
	}

	public function prices( int $product_id, string $currency ): CurrencyPrices {
		return new CurrencyPrices(
			(string) get_post_meta( $product_id, '_regular_price_' . $currency, true ),
			(string) get_post_meta( $product_id, '_sale_price_' . $currency, true )
		);
	}
}
