<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\Tokens\Domain\IntegrationCopyInterface;

defined( 'ABSPATH' ) || exit;

final readonly class InvoiceCopy implements IntegrationCopyInterface {

	public function name(): string {
		return 'Germanized Pro invoices';
	}

	public function description(): string {
		return __( 'The invoices Germanized Pro has issued, to look up and reconcile. Read-only.', 'counterhand-mcp-for-woocommerce' );
	}

	public function read_description(): string {
		return __( 'See invoice numbers, dates, payment status and totals, and which order and customer id each belongs to. Addresses and invoice files are not included.', 'counterhand-mcp-for-woocommerce' );
	}

	public function write_description(): string {
		return '';
	}
}
