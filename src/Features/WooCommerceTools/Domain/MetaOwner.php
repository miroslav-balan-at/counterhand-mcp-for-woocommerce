<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Which WordPress metadata table a resource's custom fields live in.
 *
 * Not cosmetic: is_protected_meta() takes the meta type as its second argument
 * and third parties filter it per type, so a product asked about as though it
 * were a user would get the wrong answer to the one question that matters. It
 * is also the honest way to say why customers are treated more carefully than
 * products — usermeta is where WordPress keeps roles and login sessions, and
 * postmeta is not.
 */
enum MetaOwner: string {
	case Post = 'post';
	case User = 'user';

	/** The $meta_type argument core's metadata functions expect. */
	public function meta_type(): string {
		return $this->value; // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	/**
	 * Whether writing here can affect who someone is, rather than what they
	 * bought. Used to decide how loudly a tool describes itself.
	 */
	public function carries_identity(): bool {
		return self::User === $this; // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}
}
