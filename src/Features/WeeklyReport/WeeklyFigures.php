<?php

declare( strict_types=1 );

namespace Counterhand\Features\WeeklyReport;

defined( 'ABSPATH' ) || exit;

final readonly class WeeklyFigures {

	/** @param list<TopSeller> $top_sellers */
	public function __construct(
		public ReportWeek $week,
		public SalesFigures $sales,
		public SalesFigures $previous,
		public array $top_sellers,
		public int $processing,
		public int $on_hold,
	) {}

	/**
	 * The figures a model may summarise, and nothing else. Product names come from the
	 * store, so the prompt treats them as data.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'week'           => $this->week->range(),
			'this_week'      => $this->sales->to_array(),
			'previous_week'  => $this->previous->to_array(),
			'top_sellers'    => array_map(
				static fn ( TopSeller $seller ): array => [
					'name'     => $seller->name,
					'quantity' => $seller->quantity,
				],
				$this->top_sellers
			),
			'orders_waiting' => [
				'processing' => $this->processing,
				'on_hold'    => $this->on_hold,
			],
		];
	}
}
