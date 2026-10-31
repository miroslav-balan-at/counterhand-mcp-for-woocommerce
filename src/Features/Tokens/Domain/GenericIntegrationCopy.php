<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The wording for a plugin nothing here knows more about, and for a grant read
 * back from a token while its plugin is off.
 */
final readonly class GenericIntegrationCopy implements IntegrationCopyInterface {

	public function __construct(
		private string $slug,
		private ?string $name = null,
	) {}

	public function name(): string {
		return $this->name ?? ucwords( str_replace( '-', ' ', $this->slug ) );
	}

	public function description(): string {
		return sprintf(
			/* translators: %s: name of another plugin, e.g. "Yoast SEO". */
			__( 'What %s offers to AI clients. It still checks its own permissions on every call.', 'counterhand-mcp-for-woocommerce' ),
			$this->name()
		);
	}

	public function read_description(): string {
		/* translators: %s: name of another plugin, e.g. "Yoast SEO". */
		return sprintf( __( 'See the data %s offers to AI clients.', 'counterhand-mcp-for-woocommerce' ), $this->name() );
	}

	public function write_description(): string {
		/* translators: %s: name of another plugin, e.g. "Yoast SEO". */
		return sprintf( __( 'Use what %s offers to change data on this store. Every change needs explicit confirmation.', 'counterhand-mcp-for-woocommerce' ), $this->name() );
	}
}
