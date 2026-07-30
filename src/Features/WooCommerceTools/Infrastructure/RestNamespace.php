<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * REST namespaces this plugin dispatches into.
 *
 * Carrying the namespace on the route is what lets a single gateway serve both
 * WooCommerce and WordPress core resources without a second gateway class.
 */
enum RestNamespace: string {
	case WcV3 = 'wc/v3';
	case WpV2 = 'wp/v2';

	/** Leading-slash form used to build a dispatchable path. */
	public function prefix(): string {
		return '/' . $this->value; // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}
}
