<?php

declare( strict_types=1 );

namespace Counterhand\Features\CostOfGoods;

defined( 'ABSPATH' ) || exit;

final readonly class CostedProduct {

	public function __construct(
		public string $name,
		public string $type,
		public string $price,
		public ?string $cost,
	) {}
}
