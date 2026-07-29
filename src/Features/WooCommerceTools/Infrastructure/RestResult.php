<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * One wc/v3 response: the payload plus the pagination WooCommerce reports in
 * headers.
 *
 * Those headers were previously discarded, so no list tool could tell an agent
 * how many results exist and agents had to page blindly until a short page
 * came back.
 */
final readonly class RestResult {

	public function __construct(
		public array $data,
		public ?int $total = null,
		public ?int $total_pages = null,
	) {}

	public function is_collection(): bool {
		return array_is_list( $this->data );
	}

	/**
	 * Collection members, reindexed and with any non-array entry dropped.
	 *
	 * @return list<array>
	 */
	public function items(): array {
		return array_values( array_filter( $this->data, 'is_array' ) );
	}

	/**
	 * The single resource this response describes.
	 *
	 * The wc/v3 report endpoints wrap their one object in a collection, so a
	 * list payload collapses to its first member rather than being an error.
	 */
	public function item(): array {
		if ( ! $this->is_collection() ) {
			return $this->data;
		}

		return $this->items()[0] ?? [];
	}

	/**
	 * Totals as reported by WooCommerce, ready to merge into a tool response.
	 * Empty when the endpoint is not paginated.
	 */
	public function pagination(): array {
		return array_filter(
			[
				'total'       => $this->total,
				'total_pages' => $this->total_pages,
			],
			static fn ( ?int $value ): bool => null !== $value
		);
	}
}
