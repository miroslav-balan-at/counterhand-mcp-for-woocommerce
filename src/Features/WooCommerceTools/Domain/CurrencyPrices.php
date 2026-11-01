<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

final readonly class CurrencyPrices {

	public function __construct(
		public string $regular_price,
		public string $sale_price,
	) {}

	/** @return array{regular_price: string, sale_price: string} */
	public function to_array(): array {
		return [
			'regular_price' => $this->regular_price,
			'sale_price'    => $this->sale_price,
		];
	}
}
