<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Licensing;

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
