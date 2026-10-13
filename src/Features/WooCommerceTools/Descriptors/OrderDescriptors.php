<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\MetaOwner;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Sales: /wc/v3/orders and the notes hanging off each one.
 *
 * Two resources rather than one because notes are their own controller with
 * their own permission callback — which is also what makes them probeable:
 * WooCommerce's order-notes controller checks the post type without reading
 * order_id, so the collection template answers the permission question even
 * with its placeholder unbound.
 */
final readonly class OrderDescriptors implements DescriptorProvider {

	/** Enough to recognise an order without paying for its line items. */
	private const LIST_FIELDS = [
		'id',
		'number',
		'status',
		'currency',
		'total',
		'date_created',
		'date_paid',
		'payment_method_title',
		'customer_id',
		'billing',
	];

	/** One order, whole — line items and addresses are the point of asking. */
	private const ITEM_FIELDS = [
		'id',
		'number',
		'status',
		'currency',
		'date_created',
		'date_modified',
		'date_paid',
		'date_completed',
		'discount_total',
		'shipping_total',
		'total_tax',
		'total',
		'customer_id',
		'customer_note',
		'billing',
		'shipping',
		'payment_method',
		'payment_method_title',
		'transaction_id',
		'line_items',
		'shipping_lines',
		'fee_lines',
		'coupon_lines',
		'refunds',
		'needs_payment',
		'payment_url',
	];

	private const NOTE_FIELDS = [
		'id',
		'author',
		'date_created',
		'note',
		'customer_note',
	];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			new ResourceDescriptor(
				'orders',
				ToolGroup::Orders,
				RestRoute::wc( '/orders' ),
				RestRoute::wc( '/orders/{id}' ),
				'order',
				'orders',
				$this->order_operations(),
				null,
				null,
				MetaOwner::Post
			),
			new ResourceDescriptor(
				'order_notes',
				ToolGroup::Orders,
				RestRoute::wc( '/orders/{order_id}/notes' ),
				RestRoute::wc( '/orders/{order_id}/notes/{id}' ),
				'order note',
				'order notes',
				$this->note_operations()
			),
		];
	}

	/** @return list<OperationDescriptor> */
	private function order_operations(): array {
		return [
			new OperationDescriptor(
				ToolName::from( 'list_orders' ),
				Operation::GetItems,
				new FieldProfile(
					[ 'page', 'per_page', 'status', 'customer', 'search', 'after', 'before', 'order', 'orderby' ],
					self::LIST_FIELDS,
					false
				),
				'Newest first. Totals are strings in the order currency, and statuses are WooCommerce slugs without the wc- prefix.'
			),
			new OperationDescriptor(
				ToolName::from( 'get_order' ),
				Operation::GetItem,
				new FieldProfile( [], self::ITEM_FIELDS, false ),
				'Totals are strings in the order currency. line_items carries what was bought, at the prices charged at the time.'
			),
			new OperationDescriptor(
				ToolName::from( 'create_order' ),
				Operation::CreateItem,
				// No set_paid, transaction_id or needs_confirmation: marking an
				// order paid for money nobody received is the one mistake here
				// that cannot be walked back, and allow_additional false keeps
				// them out of the schema entirely rather than policing them.
				new FieldProfile(
					[ 'customer_id', 'customer_note', 'billing', 'shipping', 'line_items', 'shipping_lines', 'fee_lines', 'coupon_lines', 'payment_method', 'payment_method_title' ],
					self::ITEM_FIELDS,
					false
				),
				'',
				'Create an order the customer has not paid for yet, and return payment_url — the link that lets them pay it. Always pending: the status is forced, because an unpaid order is the only kind this tool is for, and because the statuses that would email the customer are not it. WooCommerce prices the order itself, so send line_items as product_id and quantity and let it fill in the rest; a shipping_lines entry needs method_id, method_title and total, and is not calculated for you — read the store\'s own rates first if you do not know it. WooCommerce sends no email of its own for a new pending order, though a store\'s other plugins may. Confirm the products, quantities, shipping cost and address with the user before calling this, then give them back the total the response reports rather than one you worked out.',
				[ 'status' => 'pending' ],
			),
			new OperationDescriptor(
				ToolName::from( 'update_order_status' ),
				Operation::UpdateItem,
				new FieldProfile( [ 'status' ], [ 'id', 'number', 'status' ], false ),
				'',
				'Move an order to a different status. This may email the customer — "completed" sends the completion email — so only do it when the user has asked for the change. The allowed statuses are this store\'s own, including any a plugin has added. To record why the status changed, follow up with add_order_note.'
			),
		];
	}

	/** @return list<OperationDescriptor> */
	private function note_operations(): array {
		return [
			new OperationDescriptor(
				ToolName::from( 'get_order_notes' ),
				Operation::GetItems,
				new FieldProfile( [ 'type' ], self::NOTE_FIELDS, false ),
				'',
				'The notes on one order, identified by order_id — WooCommerce\'s own record of what happened to it, plus anything staff wrote. Pass type=customer for just the notes the customer was emailed, or type=internal for just the private ones.'
			),
			new OperationDescriptor(
				ToolName::from( 'get_order_note' ),
				Operation::GetItem,
				new FieldProfile( [], self::NOTE_FIELDS, false )
			),
			new OperationDescriptor(
				ToolName::from( 'add_order_note' ),
				Operation::CreateItem,
				new FieldProfile( [ 'note', 'customer_note' ], self::NOTE_FIELDS ),
				'',
				'Attach a note to an order, identified by order_id. Notes are private to the store by default; customer_note=true emails the note to the customer, so only pass it when the user explicitly wants them notified.'
			),
			new OperationDescriptor(
				ToolName::from( 'delete_order_note' ),
				Operation::DeleteItem,
				new FieldProfile( [ 'force' ], [ 'id', 'note' ], false ),
				'',
				'Delete a note from an order. WooCommerce does not trash notes, so force is required and the deletion is permanent. Notes WooCommerce wrote itself are part of the order\'s history — deleting those loses the record of what happened.'
			),
		];
	}
}
