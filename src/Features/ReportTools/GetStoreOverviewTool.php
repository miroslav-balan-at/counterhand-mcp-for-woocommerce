<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\ReportTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class GetStoreOverviewTool extends AbstractWcTool {

	public function name(): string {
		return 'get_store_overview';
	}

	public function description(): string {
		return 'Store snapshot: name, currency, country, product/order counts by status. Cheap and safe — call this first to orient yourself in the store.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => new \stdClass(),
			'additionalProperties' => false,
		];
	}

	public function required_scope(): ApiScope {
		return ApiScope::ReportsRead;
	}

	public function group(): ToolGroup {
		return ToolGroup::Reports;
	}

	public function execute( array $arguments ): array {
		$product_counts = (array) wp_count_posts( 'product' );

		$order_counts = [];
		foreach ( wc_get_order_statuses() as $status_key => $status_label ) {
			$slug  = str_starts_with( $status_key, 'wc-' ) ? substr( $status_key, 3 ) : $status_key;
			$count = wc_orders_count( $slug );
			if ( $count > 0 ) {
				$order_counts[ $slug ] = $count;
			}
		}

		return [
			'store_name'       => get_bloginfo( 'name' ),
			'store_url'        => home_url(),
			'currency'         => get_woocommerce_currency(),
			'country'          => WC()->countries->get_base_country(),
			'wc_version'       => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'products'         => [
				'published' => (int) ( $product_counts['publish'] ?? 0 ),
				'draft'     => (int) ( $product_counts['draft'] ?? 0 ),
			],
			'orders_by_status' => $order_counts,
		];
	}
}
