<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OrderTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class ListOrdersTool extends AbstractWcTool {

	public function name(): string {
		return 'list_orders';
	}

	public function description(): string {
		return 'List orders, newest first. Totals are strings in the order currency. Statuses are WooCommerce slugs without the wc- prefix (e.g. processing, completed, on-hold, pending, cancelled, refunded). Paginated via page/per_page.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'status'   => [
					'type'        => 'string',
					'description' => 'Order status slug without wc- prefix, or "any".',
				],
				'customer' => [
					'type'        => 'integer',
					'description' => 'Filter by customer user id.',
				],
				'search'   => [
					'type'        => 'string',
					'description' => 'Free-text search (order number, customer, address).',
				],
				'after'    => [
					'type'        => 'string',
					'format'      => 'date-time',
					'description' => 'Only orders created after this ISO8601 date-time.',
				],
				'before'   => [
					'type'        => 'string',
					'format'      => 'date-time',
					'description' => 'Only orders created before this ISO8601 date-time.',
				],
				...$this->pagination_properties(),
			],
			'additionalProperties' => false,
		];
	}

	public function required_scope(): ApiScope {
		return ApiScope::OrdersRead;
	}

	public function group(): ToolGroup {
		return ToolGroup::Orders;
	}

	public function execute( array $arguments ): array {
		$items = $this->gateway->dispatch( 'GET', '/orders', $arguments );

		$orders = array_map(
			fn ( array $order ): array => [
				'id'           => $order['id'] ?? null,
				'number'       => $order['number'] ?? '',
				'status'       => $order['status'] ?? '',
				'date_created' => $order['date_created'] ?? '',
				'total'        => $order['total'] ?? '',
				'currency'     => $order['currency'] ?? '',
				'customer_id'  => $order['customer_id'] ?? 0,
				'customer'     => trim( ( $order['billing']['first_name'] ?? '' ) . ' ' . ( $order['billing']['last_name'] ?? '' ) ),
				'email'        => $order['billing']['email'] ?? '',
				'items_count'  => is_array( $order['line_items'] ?? null ) ? count( $order['line_items'] ) : 0,
			],
			array_values( array_filter( $items, 'is_array' ) )
		);

		return [
			'orders' => $orders,
			'count'  => count( $orders ),
		];
	}
}
