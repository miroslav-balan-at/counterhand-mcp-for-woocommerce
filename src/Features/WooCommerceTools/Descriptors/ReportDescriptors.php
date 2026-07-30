<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregates: /wc/v3/reports/*.
 *
 * These are the shape the descriptor model bends least comfortably around —
 * every one is a collection route with no item behind it, answering with a
 * summary rather than a list of things you could then fetch. So all of them
 * state their own description rather than take the generated "List … in the
 * store", and none declares an item route for the factory to reach for.
 */
final readonly class ReportDescriptors implements DescriptorProvider {

	/** The headline figures. Excludes totals, which is a per-day breakdown. */
	private const SALES_FIELDS = [
		'total_sales',
		'net_sales',
		'average_sales',
		'total_orders',
		'total_items',
		'total_tax',
		'total_shipping',
		'total_refunds',
		'total_discount',
		'total_customers',
		'totals_grouped_by',
	];

	private const PERIOD_PARAMS = [ 'period', 'date_min', 'date_max' ];

	/** What each totals report counts, for the sentence describing it. */
	private const TOTALS = [
		'orders'    => [ 'get_order_totals', 'order_totals', 'orders in each status' ],
		'products'  => [ 'get_product_totals', 'product_totals', 'products of each type' ],
		'customers' => [ 'get_customer_totals', 'customer_totals', 'customers, split into those who have ordered and those who have not' ],
		'coupons'   => [ 'get_coupon_totals', 'coupon_totals', 'coupons of each discount type' ],
		'reviews'   => [ 'get_review_totals', 'review_totals', 'reviews in each moderation status' ],
	];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			new ResourceDescriptor(
				'sales_report',
				ToolGroup::Reports,
				RestRoute::wc( '/reports/sales' ),
				null,
				'sales report',
				'sales',
				[ $this->sales() ]
			),
			new ResourceDescriptor(
				'top_sellers_report',
				ToolGroup::Reports,
				RestRoute::wc( '/reports/top_sellers' ),
				null,
				'top sellers report',
				'top_sellers',
				[ $this->top_sellers() ]
			),
			...array_map(
				fn ( string $slug ): ResourceDescriptor => $this->totals( $slug ),
				array_keys( self::TOTALS )
			),
		];
	}

	/**
	 * The five /reports/{thing}/totals endpoints.
	 *
	 * Identical in shape — no arguments, one flat count per bucket — so they are
	 * built from a table rather than written out five times. Each is cheap
	 * enough to be worth calling before a listing tool, which is what the
	 * descriptions say.
	 */
	private function totals( string $slug ): ResourceDescriptor {
		[ $tool, $plural, $counts ] = self::TOTALS[ $slug ];

		return new ResourceDescriptor(
			$slug . '_totals_report',
			ToolGroup::Reports,
			RestRoute::wc( '/reports/' . $slug . '/totals' ),
			null,
			$slug . ' totals report',
			$plural,
			[
				new OperationDescriptor(
					ToolName::from( $tool ),
					Operation::GetItems,
					new FieldProfile( [], [ 'slug', 'name', 'total' ], false ),
					'',
					sprintf(
						'How many %s. One row per bucket, with a slug, a name and a count. Takes no arguments and counts the whole store, so it is a cheap way to size things up before paging through a listing tool.',
						$counts
					)
				),
			]
		);
	}

	private function sales(): OperationDescriptor {
		return new OperationDescriptor(
			ToolName::from( 'get_sales_report' ),
			Operation::GetItems,
			new FieldProfile( self::PERIOD_PARAMS, self::SALES_FIELDS, false ),
			'',
			'Sales totals — revenue, order count, items sold, refunds and discounts — over a period. Use period for a common range, or date_min and date_max (YYYY-MM-DD) for a custom one; the dates win where both are given. Amounts are strings in the shop currency.'
		);
	}

	private function top_sellers(): OperationDescriptor {
		return new OperationDescriptor(
			ToolName::from( 'get_top_sellers' ),
			Operation::GetItems,
			new FieldProfile( self::PERIOD_PARAMS, [ 'name', 'product_id', 'quantity' ], false ),
			'',
			'Best-selling products by quantity sold over a period, same range arguments as get_sales_report. Pass a product_id to get_product for the details of any one of them.'
		);
	}
}
