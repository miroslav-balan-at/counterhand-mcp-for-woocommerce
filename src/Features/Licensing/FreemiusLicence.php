<?php

declare( strict_types=1 );

namespace Counterhand\Features\Licensing;

defined( 'ABSPATH' ) || exit;

/**
 * The Freemius answer to Licence, and the only class that knows the vendor.
 *
 * Freemius is the merchant of record: it issues the key, collects VAT in every
 * jurisdiction we sell into, and serves the update package. The SDK is loaded
 * from the main plugin file — earlier than this class can run — so this only
 * reads the instance it left behind.
 */
final readonly class FreemiusLicence implements Licence {

	public function __construct( private \Freemius $freemius ) {}

	/**
	 * Null when the SDK failed to load, which is the case a caller has to plan
	 * for: a licensing outage must not take the plugin down with it.
	 */
	public static function detect(): ?self {
		if ( ! function_exists( 'counterhand_freemius' ) ) {
			return null;
		}

		$freemius = counterhand_freemius();

		return $freemius instanceof \Freemius ? new self( $freemius ) : null;
	}

	public function is_active(): bool {
		// can_use_premium_code() is the SDK's own answer and already covers
		// paying, trialling and — in a premium build — the developer's own
		// install, so it is the one question worth asking.
		return $this->freemius->can_use_premium_code();
	}

	public function upgrade_url(): string {
		return $this->freemius->get_upgrade_url();
	}

	public function account_url(): string {
		return $this->freemius->get_account_url();
	}
}
