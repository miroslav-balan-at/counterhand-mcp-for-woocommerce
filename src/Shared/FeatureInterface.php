<?php

declare( strict_types=1 );

namespace Counterhand\Shared;

defined( 'ABSPATH' ) || exit;

/**
 * A vertical feature slice: one bootstrap class per feature wires its own hooks.
 */
interface FeatureInterface {

	public function register(): void;
}
