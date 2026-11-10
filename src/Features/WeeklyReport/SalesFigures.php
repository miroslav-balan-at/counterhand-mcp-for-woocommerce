<?php

declare( strict_types=1 );

namespace Counterhand\Features\WeeklyReport;

defined( 'ABSPATH' ) || exit;

/** The headline numbers of WooCommerce's sales report, as it states them. */
final readonly class SalesFigures {

	public function __construct(
		public float $total_sales,
		public float $net_sales,
		public int $orders,
		public int $items,
		public float $refunds,
	) {}

	/** @param array<string, mixed> $row */
	public static function from_report( array $row ): self {
		return new self(
			total_sales: (float) ( $row['total_sales'] ?? 0 ),
			net_sales: (float) ( $row['net_sales'] ?? 0 ),
			orders: (int) ( $row['total_orders'] ?? 0 ),
			items: (int) ( $row['total_items'] ?? 0 ),
			refunds: (float) ( $row['total_refunds'] ?? 0 ),
		);
	}

	/** @return array<string, float|int> */
	public function to_array(): array {
		return [
			'total_sales' => $this->total_sales,
			'net_sales'   => $this->net_sales,
			'orders'      => $this->orders,
			'items_sold'  => $this->items,
			'refunds'     => $this->refunds,
		];
	}
}
