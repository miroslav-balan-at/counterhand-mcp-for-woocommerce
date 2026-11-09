<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

defined( 'ABSPATH' ) || exit;

/** A card another feature adds to the Dashboard, rendered after the built-in ones. */
interface DashboardCardInterface {

	public function render_card(): void;
}
