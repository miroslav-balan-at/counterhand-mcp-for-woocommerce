<?php

declare( strict_types=1 );

namespace AgentGateMcp\Shared\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Trims wc/v3 payloads to compact, LLM-friendly shapes: keeps only the
 * whitelisted fields, drops _links/meta_data/HTML bloat.
 */
final readonly class ResponseShaper {

	/** @param list<string> $fields */
	public function shape_item( array $item, array $fields ): array {
		$shaped = [];

		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $item ) ) {
				$shaped[ $field ] = $item[ $field ];
			}
		}

		return $shaped;
	}

	/** @param list<string> $fields */
	public function shape_list( array $items, array $fields ): array {
		return array_map(
			fn ( $item ): array => is_array( $item ) ? $this->shape_item( $item, $fields ) : [],
			array_values( $items )
		);
	}

	public function strip_html( ?string $html ): string {
		return trim( wp_strip_all_tags( (string) $html ) );
	}
}
