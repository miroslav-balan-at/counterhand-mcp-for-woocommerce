<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth\View;

use Counterhand\Features\Tokens\Domain\ApiScope;

defined( 'ABSPATH' ) || exit;

/**
 * One checkbox on the consent screen. Scopes the store withholds render
 * disabled rather than vanishing, so the admin learns here — not later as a
 * missing tool — that an area exists and where to switch it on.
 */
final readonly class ConsentScope {

	public function __construct(
		public ApiScope $scope,
		public bool $available,
		public bool $pre_checked,
	) {}

	/** Tooltip and screen-reader text for a disabled box. */
	public function unavailable_reason(): string {
		return __( 'Switched off for this store. Enable the area under Counterhand MCP → Settings, then connect this app again.', 'counterhand-mcp-for-woocommerce' );
	}
}
