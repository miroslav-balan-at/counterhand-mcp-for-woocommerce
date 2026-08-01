<?php

declare( strict_types=1 );

namespace Counterhand\Features\Licensing;

defined( 'ABSPATH' ) || exit;

/**
 * What the store is licensed to do, asked without naming Freemius.
 *
 * Every gate in the plugin reads this rather than calling the SDK directly, so
 * the licensing vendor stays replaceable and the rest of the codebase never
 * learns a second vocabulary for "may I".
 */
interface Licence {

	/**
	 * Whether paid functionality may run.
	 *
	 * True for a paying customer, a trial, and — deliberately — for a local or
	 * staging install, because a developer setting the plugin up should not hit
	 * a paywall before they have seen it work.
	 */
	public function is_active(): bool;

	/** Where to send someone whose licence will not activate. */
	public function upgrade_url(): string;

	/** The account screen, for managing or cancelling a subscription. */
	public function account_url(): string;
}
