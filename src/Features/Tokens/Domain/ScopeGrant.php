<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Tokens\Domain;

use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * What a token holds over one tool group: the group, and whether the grant
 * extends past reading.
 *
 * Two scopes collapse into one of these because that is how people ask the
 * question — "can this connection touch my orders?" is one question, not two.
 */
final readonly class ScopeGrant {

	public function __construct(
		public ToolGroup $group,
		public bool $writable,
	) {}

	public function label(): string {
		return $this->group->label();
	}

	/**
	 * The group name, marked when writes are included.
	 *
	 * The marker is a suffix rather than a separate badge so the pair never
	 * wraps apart from each other in a narrow table column.
	 */
	public function badge(): string {
		if ( ! $this->writable ) {
			return $this->label();
		}

		/* translators: %s: tool group name, e.g. "Orders". Marks a grant that includes writes. */
		return sprintf( __( '%s ·W', 'agentgate-mcp-for-woocommerce' ), $this->label() );
	}
}
