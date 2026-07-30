<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * A rule about what a tool's arguments may say, checked before dispatch.
 *
 * WooCommerce authorises the *route* — may this user change settings at all —
 * and then does whatever the arguments ask. For most of the surface that is
 * enough, because the route is the whole of the risk. For a handful it is not:
 * "change a setting" spans the shop's address and the payment gateway's secret
 * key, and "run a maintenance tool" spans clearing a cache and resetting every
 * user's role. Those distinctions live in the arguments, so they have to be
 * judged there.
 *
 * An interface rather than a branch inside GeneratedTool: the point of that
 * class is that one execute() serves the whole surface, and it keeps that
 * property only if the varying behaviour sits on a collaborator. A descriptor
 * that needs no rule carries no policy and pays nothing.
 */
interface ArgumentPolicy {

	/**
	 * @param array<string, mixed> $arguments Already schema-validated arguments.
	 */
	public function verdict( array $arguments ): Verdict;
}
