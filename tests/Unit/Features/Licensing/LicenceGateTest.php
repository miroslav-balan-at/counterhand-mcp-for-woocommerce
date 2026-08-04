<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Licensing;

use Counterhand\Features\Licensing\FreemiusLicence;
use Counterhand\Features\Licensing\Licence;
use Counterhand\Features\Licensing\UnlicensedFallback;
use Counterhand\Tests\Unit\TestCase;

/**
 * What an unlicensed store is still allowed to do.
 *
 * The rule worth pinning is that a licensing fault fails *open*. Getting this
 * backwards would take a paying shop's MCP endpoint offline because a vendor
 * file failed to load — an outage the owner cannot diagnose and did not cause.
 */
final class LicenceGateTest extends TestCase {

	public function test_a_missing_sdk_leaves_the_plugin_working(): void {
		$licence = new UnlicensedFallback();

		$this->assertTrue(
			$licence->is_active(),
			'A licensing outage is our fault, not the store\'s: it must not disable a paid feature.'
		);
	}

	public function test_the_fallback_still_offers_somewhere_to_go(): void {
		$licence = new UnlicensedFallback();

		$this->assertStringStartsWith( 'https://', $licence->upgrade_url() );
	}

	/**
	 * The paid answer comes from the SDK and nowhere else.
	 *
	 * can_use_premium_code() already folds together paying, trialling and the
	 * developer's own install, so asking anything more here would either
	 * duplicate the SDK's logic or contradict it.
	 */
	public function test_a_licensed_store_is_active(): void {
		$licence = new FreemiusLicence( $this->freemius( true ) );

		$this->assertTrue( $licence->is_active() );
	}

	public function test_an_unlicensed_store_is_not_active(): void {
		$licence = new FreemiusLicence( $this->freemius( false ) );

		$this->assertFalse(
			$licence->is_active(),
			'A store that has not paid must not reach the endpoint, or the licence buys nothing.'
		);
	}

	/**
	 * A licensing answer of "no" is not the same as a licensing fault: the first
	 * withholds the endpoint, the second must not. Both arrive as is_active(),
	 * so the difference lives in which implementation the caller holds.
	 */
	public function test_a_refusal_and_an_outage_are_different_answers(): void {
		$this->assertFalse( ( new FreemiusLicence( $this->freemius( false ) ) )->is_active() );
		$this->assertTrue( ( new UnlicensedFallback() )->is_active() );
	}

	private function freemius( bool $premium ): \Freemius {
		return new class( $premium ) extends \Freemius {
			public function __construct( private bool $premium ) {}

			public function can_use_premium_code(): bool {
				return $this->premium;
			}

			public function get_upgrade_url(): string {
				return 'https://checkout.freemius.com/upgrade';
			}

			public function get_account_url(): string {
				return 'https://example.test/wp-admin/admin.php?page=counterhand-mcp-account';
			}
		};
	}

	/** The contract every gate reads, so a second vendor could satisfy it. */
	public function test_the_contract_is_answerable_without_freemius(): void {
		$licence = new class() implements Licence {
			public function is_active(): bool {
				return false;
			}

			public function upgrade_url(): string {
				return 'https://example.test/buy';
			}

			public function account_url(): string {
				return 'https://example.test/account';
			}
		};

		$this->assertFalse( $licence->is_active() );
		$this->assertSame( 'https://example.test/buy', $licence->upgrade_url() );
	}
}
