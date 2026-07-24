<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OrderTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class AddOrderNoteTool extends AbstractWcTool {

	public function name(): string {
		return 'add_order_note';
	}

	public function description(): string {
		return 'Add a note to an order. Private by default; set customer_note=true to email the note to the customer — only do that when the user explicitly wants the customer notified.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'id'            => [
					'type'        => 'integer',
					'description' => 'Order id.',
				],
				'note'          => [
					'type'      => 'string',
					'minLength' => 1,
				],
				'customer_note' => [
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'true sends the note to the customer by email.',
				],
			],
			'required'             => [ 'id', 'note' ],
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
		$created = $this->gateway->dispatch( 'POST', '/orders/' . (int) $arguments['id'] . '/notes', [
			'note'          => (string) $arguments['note'],
			'customer_note' => (bool) ( $arguments['customer_note'] ?? false ),
		] );

		return [
			'note_id'       => $created['id'] ?? null,
			'customer_note' => $created['customer_note'] ?? false,
			'date_created'  => $created['date_created'] ?? '',
		];
	}
}
