<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * HTTP methods this plugin dispatches.
 *
 * An enum rather than a string so read/write intent has one home: the
 * capability probe picks GET-vs-POST on the collection route from it, and no
 * call site can drift into a lower-case verb the REST router will not match.
 */
enum RestMethod: string {
	case Get    = 'GET';
	case Post   = 'POST';
	case Put    = 'PUT';
	case Delete = 'DELETE';

	public function is_write(): bool {
		return self::Get !== $this; // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}
}
