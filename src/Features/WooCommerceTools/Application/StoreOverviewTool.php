<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Application;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\ToolGroup;
use AgentGateMcp\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The one tool that is not a wc/v3 resource operation.
 *
 * Everything else in this slice is generated because it maps 1:1 onto a route.
 * This one fans out across four sources that have no single endpoint between
 * them — site options, WooCommerce's country settings, the post counts and the
 * order counts — so there is no descriptor that could describe it and no
 * schema to derive. It stays hand-written, and stays small.
 *
 * It earns its place by being the cheapest possible first call: an agent that
 * runs it once knows the currency, the country and the rough size of the store,
 * and stops guessing at all three for the rest of the conversation.
 */
final readonly class StoreOverviewTool implements ToolInterface {

	public function name(): string {
		return 'get_store_overview';
	}

	public function description(): string {
		return 'Store snapshot: name, currency, country, product/order counts by status. Cheap and safe — call this first to orient yourself in the store.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			// stdClass, not [], so json_encode emits {} — an empty PHP array
			// would serialize as [] and strict MCP clients reject that.
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

	/**
	 * No route to probe, and nothing here a token owner cannot already see:
	 * reaching this plugin at all requires manage_woocommerce.
	 */
	public function is_available(): bool {
		return true;
	}

	public function execute( array $arguments ): array {
		$product_counts = (array) wp_count_posts( 'product' );

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
			'orders_by_status' => $this->order_counts(),
		];
	}

	/**
	 * Statuses with nothing in them are omitted rather than reported as zero —
	 * a store with fifteen registered statuses would otherwise spend most of
	 * this response saying nothing happened.
	 *
	 * @return array<string, int>
	 */
	private function order_counts(): array {
		$counts = [];

		foreach ( array_keys( wc_get_order_statuses() ) as $status_key ) {
			$slug  = str_starts_with( (string) $status_key, 'wc-' ) ? substr( (string) $status_key, 3 ) : (string) $status_key;
			$count = wc_orders_count( $slug );

			if ( $count > 0 ) {
				$counts[ $slug ] = $count;
			}
		}

		return $counts;
	}
}
