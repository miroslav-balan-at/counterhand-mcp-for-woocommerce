<?php

declare( strict_types=1 );

namespace AgentGateMcp\Shared;

defined( 'ABSPATH' ) || exit;

/**
 * A vertical feature slice: one bootstrap class per feature wires its own hooks.
 */
interface FeatureInterface {

	public function register(): void;
}
