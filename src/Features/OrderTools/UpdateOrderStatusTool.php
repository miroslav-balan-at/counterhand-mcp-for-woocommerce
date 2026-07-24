<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OrderTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Exception\ToolCallException;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class UpdateOrderStatusTool extends AbstractWcTool {

	public function name(): string {
		return 'update_order_status';
	}

	public function description(): string {
		return 'Change the status of an order. Status is a WooCommerce slug without the wc- prefix. This may trigger customer emails (e.g. "completed" sends the completion email). Optionally attach a private note explaining the change.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'id'     => [
					'type'        => 'integer',
					'description' => 'Order id.',
				],
				'status' => [
					'type'        => 'string',
					'description' => 'Target status slug without wc- prefix, e.g. processing, completed, on-hold, cancelled, refunded.',
				],
				'note'   => [
					'type'        => 'string',
					'description' => 'Optional private order note recording why the status changed.',
				],
			],
			'required'             => [ 'id', 'status' ],
			'additionalProperties' => false,
		];
	}

	public function required_scope(): ApiScope {
		return ApiScope::OrdersWrite;
	}

	public function group(): ToolGroup {
		return ToolGroup::Orders;
	}

	public function execute( array $arguments ): array {
		$status = (string) $arguments['status'];

		// Validate against the store's live status list (plugins add custom ones).
		$valid_statuses = array_map(
			static fn ( string $key ): string => str_starts_with( $key, 'wc-' ) ? substr( $key, 3 ) : $key,
			array_keys( wc_get_order_statuses() )
		);

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			throw new ToolCallException(
				sprintf(
					'Unknown order status "%s". Valid statuses for this store: %s.',
					$status,
					implode( ', ', $valid_statuses )
				)
			);
		}

		$order_id = (int) $arguments['id'];

		$updated = $this->gateway->dispatch( 'PUT', '/orders/' . $order_id, [ 'status' => $status ] );

		if ( isset( $arguments['note'] ) && '' !== trim( (string) $arguments['note'] ) ) {
			$this->gateway->dispatch(
				'POST',
				'/orders/' . $order_id . '/notes',
				[
					'note'          => (string) $arguments['note'],
					'customer_note' => false,
				]
			);
		}

		return [
			'id'     => $updated['id'] ?? null,
			'number' => $updated['number'] ?? '',
			'status' => $updated['status'] ?? '',
		];
	}
}
