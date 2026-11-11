<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth\View;

use Counterhand\Features\Settings\AdminScreen;
use Counterhand\Features\Tokens\Domain\ScopeInterface;

defined( 'ABSPATH' ) || exit;

/**
 * One checkbox on the consent screen. The screen shows the whole catalogue —
 * grantable or not — so the admin learns here, not later as a missing tool,
 * what exists, what is off, and where to switch it on.
 */
final readonly class ConsentScope {

	public function __construct(
		public ScopeInterface $scope,
		public ConsentAvailability $availability,
		public bool $pre_checked,
	) {}

	public function available(): bool {
		return ConsentAvailability::Grantable === $this->availability;
	}

	/** Tooltip and screen-reader text for a disabled box. */
	public function unavailable_reason(): string {
		return match ( $this->availability ) {
			ConsentAvailability::Grantable    => '',
			ConsentAvailability::SwitchedOff  => sprintf(
				/* translators: 1: the plugin's menu name, 2: the Access screen's name. */
				__( 'Switched off for this store. Switch the area on under %1$s › %2$s, then connect this app again.', 'counterhand-mcp-for-woocommerce' ),
				__( 'Counterhand MCP', 'counterhand-mcp-for-woocommerce' ),
				AdminScreen::Access->menu_title()
			),
			ConsentAvailability::NotRequested => __( 'The app did not ask for this, so it cannot be granted here. Reconnect the app to request it.', 'counterhand-mcp-for-woocommerce' ),
		};
	}
}
