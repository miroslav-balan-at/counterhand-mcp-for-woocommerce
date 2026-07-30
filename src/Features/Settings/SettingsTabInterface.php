<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * A feature-owned body for one of the admin shell's screens.
 *
 * The shell renders the page chrome and delegates the body through this
 * contract, so it can hold sibling features without naming their classes.
 */
interface SettingsTabInterface {

	public function render_tab(): void;
}
