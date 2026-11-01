<?php

declare( strict_types=1 );

namespace Counterhand\Features\CostOfGoods;

defined( 'ABSPATH' ) || exit;

final readonly class CostedOrder {

	/**
	 * @param list<CostedLine> $lines
	 * @param float            $refunded_revenue What refunds took off the lines, as a positive amount.
	 * @param float            $refunded_cost    The cost those refunds gave back, as a positive amount.
	 * @param bool             $has_unallocated_refund A refund of an amount rather than of items, so its tax is not known.
	 * @param bool             $counts_as_earned Paid, on hold or refunded, so that a full refund reads as earning nothing rather than as unknown.
	 */
	public function __construct(
		public string $status,
		public string $total,
		public string $currency,
		public string $store_currency,
		public array $lines,
		public float $refunded_revenue,
		public float $refunded_cost,
		public bool $has_unallocated_refund,
		public bool $counts_as_earned,
	) {}

	public function revenue(): float {
		return array_sum( array_map( static fn ( CostedLine $line ): float => $line->total, $this->lines ) ) - $this->refunded_revenue;
	}

	public function cost(): float {
		return array_sum( array_map( static fn ( CostedLine $line ): float => (float) $line->cost, $this->lines ) ) - $this->refunded_cost;
	}

	/** Why no profit can be stated, or null when one can. A wrong number is worse than none. */
	public function why_no_profit(): ?string {
		return match ( true ) {
			[] === $this->lines => 'The order has no items.',
			! $this->counts_as_earned => sprintf( 'An order that is %s has earned nothing yet.', $this->status ),
			$this->currency !== $this->store_currency => sprintf( 'The order is in %s and costs are in %s, and the rate it was placed at is not known.', $this->currency, $this->store_currency ),
			[] !== array_filter( $this->lines, static fn ( CostedLine $line ): bool => null === $line->cost ) => 'A line has no cost recorded.',
			$this->has_unallocated_refund => 'A refund was of an amount rather than of items, so its effect on profit is not known.',
			default => null,
		};
	}
}
