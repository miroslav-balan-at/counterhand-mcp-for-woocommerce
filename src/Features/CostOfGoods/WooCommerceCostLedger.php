<?php

declare( strict_types=1 );

namespace Counterhand\Features\CostOfGoods;

defined( 'ABSPATH' ) || exit;

final readonly class WooCommerceCostLedger implements CostLedgerInterface {

	private const PRODUCT_COST = '_wc_cog_cost';

	/** Yes on a variation lets a later default on its parent overwrite it. */
	private const FOLLOWS_DEFAULT = '_wc_cog_default_cost';

	private const LINE_TOTAL_COST = '_wc_cog_item_total_cost';

	public function is_installed(): bool {
		return class_exists( \WC_COG_Product::class );
	}

	public function user_can_read_costs(): bool {
		return (bool) wc_rest_check_post_permissions( 'product', 'read' );
	}

	public function user_can_edit_costs(): bool {
		return (bool) wc_rest_check_post_permissions( 'product', 'create' );
	}

	public function user_can_read_product( int $id ): bool {
		return (bool) wc_rest_check_post_permissions( 'product', 'read', $id );
	}

	public function user_can_edit_product( int $id ): bool {
		return (bool) wc_rest_check_post_permissions( 'product', 'edit', $id );
	}

	public function user_can_read_order( int $id ): bool {
		return (bool) wc_rest_check_post_permissions( 'shop_order', 'read', $id );
	}

	public function product( int $id ): ?CostedProduct {
		$product = wc_get_product( $id );

		if ( ! $product instanceof \WC_Product ) {
			return null;
		}

		$cost = \WC_COG_Product::get_cost( $product );

		return new CostedProduct( $product->get_name(), $product->get_type(), (string) $product->get_price( 'edit' ), '' === (string) $cost ? null : (string) $cost );
	}

	/** Costs are not synchronized across WPML's languages, and an order of a translation reads that translation's. */
	public function set_cost( int $id, string $cost ): void {
		foreach ( $this->every_language( $id ) as $language_id ) {
			$product = wc_get_product( $language_id );

			if ( $product instanceof \WC_Product ) {
				$product->update_meta_data( self::PRODUCT_COST, wc_format_decimal( $cost ) );
				$product->update_meta_data( self::FOLLOWS_DEFAULT, 'no' );
				$product->save();
			}
		}
	}

	public function order( int $id ): ?CostedOrder {
		$order = wc_get_order( $id );

		if ( ! $order instanceof \WC_Order ) {
			return null;
		}

		$lines = [];

		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof \WC_Order_Item_Product ) {
				$lines[] = new CostedLine( $item->get_product_id(), $item->get_name(), (float) $item->get_quantity(), (float) $item->get_total(), self::recorded_cost( $item ) );
			}
		}

		$refunded_revenue = 0.0;
		$refunded_cost    = 0.0;
		$unallocated      = false;

		foreach ( $order->get_refunds() as $refund ) {
			$items       = array_filter( $refund->get_items(), static fn ( object $item ): bool => $item instanceof \WC_Order_Item_Product );
			$unallocated = $unallocated || ( [] === $items && 0.0 !== (float) $refund->get_total() );

			foreach ( $items as $item ) {
				$refunded_revenue += abs( (float) $item->get_total() );
				$refunded_cost    += abs( (float) $item->get_meta( self::LINE_TOTAL_COST ) );
			}
		}

		return new CostedOrder(
			$order->get_status(),
			(string) $order->get_total(),
			$order->get_currency(),
			(string) get_option( 'woocommerce_currency' ),
			$lines,
			$refunded_revenue,
			$refunded_cost,
			$unallocated,
			in_array( $order->get_status(), [ ...wc_get_is_paid_statuses(), 'on-hold', 'refunded' ], true )
		);
	}

	/** The plugin stores 0 for a product with no cost, so 0 cannot be told from unknown. */
	private static function recorded_cost( \WC_Order_Item_Product $item ): ?float {
		$cost = (float) $item->get_meta( self::LINE_TOTAL_COST );

		return $cost > 0 ? $cost : null;
	}

	/** @return list<int> */
	private function every_language( int $id ): array {
		$type    = 'post_' . get_post_type( $id );
		$details = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $id,
				'element_type' => $type,
			]
		);

		if ( ! is_object( $details ) || empty( $details->trid ) ) {
			return [ $id ];
		}

		$translations = apply_filters( 'wpml_get_element_translations', null, $details->trid, $type );

		return is_array( $translations ) ? array_map( static fn ( object $translation ): int => (int) $translation->element_id, array_values( $translations ) ) : [ $id ];
	}
}
