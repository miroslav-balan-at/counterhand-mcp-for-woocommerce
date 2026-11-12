<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

defined( 'ABSPATH' ) || exit;

/** A card another feature adds to Overview, rendered after the built-in ones. */
interface OverviewCardInterface {

	public function render_card(): void;
}
