<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\Tokens\Domain\IntegrationCopyInterface;

defined( 'ABSPATH' ) || exit;

final readonly class ContactForm7Copy implements IntegrationCopyInterface {

	public function name(): string {
		return 'Contact Form 7';
	}

	public function description(): string {
		return __( 'Your contact forms and who their submissions are emailed to. Submissions themselves are not stored by Contact Form 7.', 'counterhand-mcp-for-woocommerce' );
	}

	public function read_description(): string {
		return __( 'See your contact forms, including who their submissions are emailed to.', 'counterhand-mcp-for-woocommerce' );
	}

	public function write_description(): string {
		return __( 'Create, edit and permanently delete contact forms, including who their submissions are emailed to. Every change needs explicit confirmation.', 'counterhand-mcp-for-woocommerce' );
	}
}
