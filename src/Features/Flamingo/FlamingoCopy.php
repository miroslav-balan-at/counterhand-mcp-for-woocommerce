<?php

declare( strict_types=1 );

namespace Counterhand\Features\Flamingo;

use Counterhand\Features\Tokens\Domain\IntegrationCopyInterface;

defined( 'ABSPATH' ) || exit;

final readonly class FlamingoCopy implements IntegrationCopyInterface {

	public function name(): string {
		return 'Flamingo';
	}

	public function description(): string {
		return __( 'Messages people sent through your forms, stored by Flamingo. These are personal data.', 'counterhand-mcp-for-woocommerce' );
	}

	public function read_description(): string {
		return __( 'Read messages people sent through your forms, including their names, email addresses and everything they wrote.', 'counterhand-mcp-for-woocommerce' );
	}

	public function write_description(): string {
		return __( 'Move stored form messages to the trash. Every removal needs explicit confirmation.', 'counterhand-mcp-for-woocommerce' );
	}
}
