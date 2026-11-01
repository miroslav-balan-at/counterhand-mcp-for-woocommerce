<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;

defined( 'ABSPATH' ) || exit;

/**
 * Germanized Pro's invoices, whose routes exist only while invoicing is switched on.
 *
 * Read-only: an invoice is a legal document, numbered in sequence, and correcting one means
 * cancelling it. The field list leaves out the address, and the path and filename, which
 * name a location on the server. search is left out too: it looks through addresses, so
 * it would confirm where someone lives even though none is returned.
 */
final readonly class InvoiceDescriptors implements DescriptorProvider {

	private const NAMESPACE = 'sab/v1';

	private const OUTPUT = [ 'id', 'number', 'formatted_number', 'status', 'payment_status', 'date_created', 'date_due', 'date_paid', 'order_id', 'order_number', 'customer_id', 'currency', 'total', 'total_tax' ];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			new ResourceDescriptor(
				'invoices',
				IntegrationGroup::of( 'germanized-pro', writable: false, copy: new InvoiceCopy() ),
				RestRoute::of( self::NAMESPACE, '/invoices' ),
				RestRoute::of( self::NAMESPACE, '/invoices/{id}' ),
				'invoice',
				'invoices',
				[
					new OperationDescriptor(
						ToolName::from( 'get_invoices' ),
						Operation::GetItems,
						new FieldProfile( [ 'page', 'per_page', 'after', 'before', 'include', 'status', 'payment_status', 'number', 'formatted_number', 'order', 'orderby' ], self::OUTPUT, false ),
						description_override: 'List invoices issued by Germanized Pro, newest first. payment_status finds unpaid ones. Paginated: pass page and per_page.'
					),
					new OperationDescriptor(
						ToolName::from( 'get_invoice' ),
						Operation::GetItem,
						new FieldProfile( [], self::OUTPUT, false ),
						description_override: 'Read one invoice, by id. Its address and file are not included.'
					),
				]
			),
		];
	}
}
