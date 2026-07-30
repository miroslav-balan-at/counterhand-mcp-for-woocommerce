<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Whether an operation reads the store or changes it.
 *
 * Read and write are the axis everything else is cut along: the scope a token
 * must carry, the toggle a store owner flips, and the permission check worth
 * asking WooCommerce about. Deriving all three from one value is what keeps a
 * descriptor from being able to declare a write tool that asks for a read
 * scope, or is gated by a read capability.
 */
enum ToolIntent: string {
	case Read  = 'read';
	case Write = 'write';

	/**
	 * The scope a token must hold, or null for a group WooCommerce exposes
	 * read-only — a write operation declared on such a group is a descriptor
	 * bug, and the factory refuses to build it.
	 */
	public function scope_of( ToolGroup $group ): ?ApiScope {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Read  => $group->read_scope(),
			self::Write => $group->write_scope(),
		};
	}

	/**
	 * The method to ask WooCommerce about when deciding whether to offer a tool.
	 *
	 * Every write probes the create check, because delete_post is a meta
	 * capability with no id-free form — map_meta_cap() returns do_not_allow for
	 * one asked without an id, which would hide every delete tool from
	 * administrators too. The approximation is safe in the direction that
	 * matters: this decides what is advertised, and rest_do_request() still runs
	 * the exact, id-aware check before anything happens.
	 */
	public function probe_method(): RestMethod {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Read  => RestMethod::Get,
			self::Write => RestMethod::Post,
		};
	}
}
