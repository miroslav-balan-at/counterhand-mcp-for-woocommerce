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
 * Refunds against an order: /wc/v3/orders/{order_id}/refunds.
 *
 * The most consequential group in this release, and the reason it is not part
 * of Orders. create_refund with api_refund true asks the payment gateway to
 * move real money back to the customer, and no tool here can undo that — a
 * store owner granting "update orders" is not thereby agreeing to it.
 *
 * Shipped disabled like every new group, and the write axis ships disabled on
 * top of that.
 */
final readonly class RefundDescriptors implements DescriptorProvider {

	private const LIST_FIELDS = [
		'id',
		'date_created',
		'amount',
		'reason',
		'refunded_by',
	];

	private const ITEM_FIELDS = [
		'id',
		'date_created',
		'amount',
		'reason',
		'refunded_by',
		'refunded_payment',
		'line_items',
	];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			new ResourceDescriptor(
				'order_refunds',
				ToolGroup::Refunds,
				RestRoute::wc( '/orders/{order_id}/refunds' ),
				RestRoute::wc( '/orders/{order_id}/refunds/{id}' ),
				'refund',
				'refunds',
				$this->operations()
			),
		];
	}

	/** @return list<OperationDescriptor> */
	private function operations(): array {
		return [
			new OperationDescriptor(
				ToolName::from( 'get_order_refunds' ),
				Operation::GetItems,
				new FieldProfile( [ 'page', 'per_page', 'order', 'orderby' ], self::LIST_FIELDS, false ),
				'',
				'List the refunds already issued against one order, identified by order_id. Amounts are strings in the order currency. An order with no refunds returns an empty list, which is the normal case.'
			),
			new OperationDescriptor(
				ToolName::from( 'get_order_refund' ),
				Operation::GetItem,
				new FieldProfile( [], self::ITEM_FIELDS, false ),
				'line_items shows which parts of the order the refund covered.'
			),
			new OperationDescriptor(
				ToolName::from( 'create_order_refund' ),
				Operation::CreateItem,
				// No line_items: WooCommerce accepts it on the refund object but
				// does not declare it as a creatable argument, so naming it here
				// would advertise a field the route never validates.
				new FieldProfile( [ 'amount', 'reason', 'api_refund', 'api_restock' ], self::ITEM_FIELDS ),
				'',
				'SAFETY: this issues a real refund against the order and cannot be undone here. With api_refund true — WooCommerce\'s default — the payment gateway returns the money to the customer immediately; pass false to record the refund in WooCommerce only, for money already returned by other means. Confirm the order id, the amount and which of the two you mean with the user before calling this. amount is a decimal string in the order currency and may not exceed what remains refundable. This refunds an amount, not particular items — to restock, pass api_restock.'
			),
			new OperationDescriptor(
				ToolName::from( 'delete_order_refund' ),
				Operation::DeleteItem,
				new FieldProfile( [ 'force' ], [ 'id', 'amount' ], false ),
				'',
				'Delete the refund record. This does not reverse the payment — money already returned to the customer stays returned, and the order simply stops showing it as refunded.'
			),
		];
	}
}
