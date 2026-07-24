<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OrderTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class GetOrderTool extends AbstractWcTool {

	public function name(): string {
		return 'get_order';
	}

	public function description(): string {
		return 'Get one order by id with line items, addresses, shipping and payment details. Totals are strings in the order currency.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'id' => [
					'type'        => 'integer',
					'description' => 'Order id.',
				],
			],
			'required'             => [ 'id' ],
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
		$order = $this->gateway->dispatch( 'GET', '/orders/' . (int) $arguments['id'] );

		return [
			'id'                   => $order['id'] ?? null,
			'number'               => $order['number'] ?? '',
			'status'               => $order['status'] ?? '',
			'date_created'         => $order['date_created'] ?? '',
			'currency'             => $order['currency'] ?? '',
			'total'                => $order['total'] ?? '',
			'shipping_total'       => $order['shipping_total'] ?? '',
			'total_tax'            => $order['total_tax'] ?? '',
			'payment_method_title' => $order['payment_method_title'] ?? '',
			'customer_id'          => $order['customer_id'] ?? 0,
			'customer_note'        => $order['customer_note'] ?? '',
			'billing'              => $order['billing'] ?? [],
			'shipping'             => $order['shipping'] ?? [],
			'line_items'           => array_map(
				static fn ( array $line_item ): array => [
					'product_id' => $line_item['product_id'] ?? null,
					'name'       => $line_item['name'] ?? '',
					'sku'        => $line_item['sku'] ?? '',
					'quantity'   => $line_item['quantity'] ?? 0,
					'price'      => $line_item['price'] ?? 0,
					'total'      => $line_item['total'] ?? '',
				],
				is_array( $order['line_items'] ?? null ) ? array_values( array_filter( $order['line_items'], 'is_array' ) ) : []
			),
			'shipping_lines'       => array_map(
				static fn ( array $shipping_line ): array => [
					'method_title' => $shipping_line['method_title'] ?? '',
					'total'        => $shipping_line['total'] ?? '',
				],
				is_array( $order['shipping_lines'] ?? null ) ? array_values( array_filter( $order['shipping_lines'], 'is_array' ) ) : []
			),
		];
	}
}
