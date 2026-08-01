<?php

declare( strict_types=1 );

namespace Counterhand\Features\Licensing;

defined( 'ABSPATH' ) || exit;

/**
 * The answer when the licensing SDK is not there at all.
 *
 * Fails open, not closed. A missing or broken SDK is our fault, not the
 * store's, and a shop that has paid should not lose its MCP endpoint because a
 * vendor file failed to load — a live storefront would break in a way the
 * owner cannot diagnose. Enforcement lives in the paid build; this only
 * decides what happens when enforcement itself is unavailable.
 */
final readonly class UnlicensedFallback implements Licence {

	public function is_active(): bool {
		return true;
	}

	public function upgrade_url(): string {
		return 'https://counterhand.balan.at/#licence';
	}

	public function account_url(): string {
		return admin_url( 'admin.php?page=counterhand-mcp-settings' );
	}
}
