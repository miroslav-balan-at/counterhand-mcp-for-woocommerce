<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\Tokens\Domain\IntegrationCopyInterface;

defined( 'ABSPATH' ) || exit;

final readonly class GermanizedCopy implements IntegrationCopyInterface {

	public function name(): string {
		return 'Germanized';
	}

	public function description(): string {
		return __( 'The legal details German and EU shops must show: delivery times, unit prices, deposits, manufacturers, allergens and nutrients. Bank details are never included.', 'counterhand-mcp-for-woocommerce' );
	}

	public function read_description(): string {
		return __( 'See delivery times, units, price labels, deposit types, manufacturers, allergens and nutrients, and these details on any product, including drafts.', 'counterhand-mcp-for-woocommerce' );
	}

	public function write_description(): string {
		return __( 'Manage those lists, and set these details on any product, without being able to change its price, status or tax status. A deposit set on a product is added to what customers pay at checkout, and getting legal details wrong has legal consequences, so changes to products and deposits and every deletion need explicit confirmation.', 'counterhand-mcp-for-woocommerce' );
	}
}
