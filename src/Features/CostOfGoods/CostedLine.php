<?php

declare( strict_types=1 );

namespace Counterhand\Features\CostOfGoods;

defined( 'ABSPATH' ) || exit;

final readonly class CostedLine {

	public function __construct(
		public int $product_id,
		public string $name,
		public float $quantity,
		public float $total,
		public ?float $cost,
	) {}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return [
			'product_id' => $this->product_id,
			'name'       => $this->name,
			'quantity'   => $this->quantity,
			'total'      => $this->total,
			'cost'       => $this->cost,
		];
	}
}
