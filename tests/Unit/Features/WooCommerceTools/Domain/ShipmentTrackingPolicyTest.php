<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Brain\Monkey\Functions;
use Counterhand\Features\WooCommerceTools\Domain\ShipmentTrackingPolicy;
use Counterhand\Tests\Unit\TestCase;

final class ShipmentTrackingPolicyTest extends TestCase {

	/**
	 * @dataProvider verdicts
	 * @param array<string, string> $arguments
	 */
	public function test_it_holds_a_tracking_number_to_what_will_really_show( array $arguments, bool $allowed ): void {
		Functions\when( 'rest_do_request' )->justReturn(
			new class() {
				/** @return array<string, array<string, string>> */
				public function get_data(): array {
					return [
						'Germany' => [ 'DHL Intraship (DE)' => 'https://dhl.test/%1$s' ],
						[ 'tracking_number' => 'ALREADY' ],
					];
				}
			}
		);
		Functions\when( 'sanitize_title' )->alias( static fn ( string $title ): string => strtolower( trim( (string) preg_replace( '/[^a-z0-9]+/i', '-', $title ), '-' ) ) );

		$this->assertSame( $allowed, ( new ShipmentTrackingPolicy( '/providers' ) )->verdict( $arguments )->allowed );
	}

	/** @return array<string, array{array<string, string>, bool}> */
	public static function verdicts(): array {
		return [
			'a known carrier'               => [
				[
					'tracking_provider' => 'dhl-intraship-de',
					'tracking_number'   => 'A',
				],
				true,
			],
			'its display name'              => [
				[
					'tracking_provider' => 'DHL Intraship (DE)',
					'tracking_number'   => 'A',
				],
				true,
			],
			'unknown, saved as nothing'     => [
				[
					'tracking_provider' => 'no-such',
					'tracking_number'   => 'A',
				],
				false,
			],
			'no number'                     => [ [ 'tracking_provider' => 'dhl-intraship-de' ], false ],
			'no carrier'                    => [ [ 'tracking_number' => 'A' ], false ],
			'a custom carrier with https'   => [
				[
					'custom_tracking_provider' => 'X',
					'custom_tracking_link'     => 'https://x.test/%1$s',
					'tracking_number'          => 'A',
				],
				true,
			],
			'a custom carrier, plain http'  => [
				[
					'custom_tracking_provider' => 'X',
					'custom_tracking_link'     => 'http://x.test',
					'tracking_number'          => 'A',
				],
				false,
			],
			'a bad date, saved as today'    => [
				[
					'tracking_provider' => 'dhl-intraship-de',
					'tracking_number'   => 'A',
					'date_shipped'      => 'tomorrow',
				],
				false,
			],
			'a good date'                   => [
				[
					'tracking_provider' => 'dhl-intraship-de',
					'tracking_number'   => 'A',
					'date_shipped'      => '2026-10-31',
				],
				true,
			],
			'a number already on the order' => [
				[
					'tracking_provider' => 'dhl-intraship-de',
					'tracking_number'   => 'ALREADY',
					'order_id'          => '5',
				],
				false,
			],
			'a custom carrier, no link'     => [
				[
					'custom_tracking_provider' => 'X',
					'tracking_number'          => 'A',
				],
				false,
			],
			'a script link'                 => [
				[
					'custom_tracking_provider' => 'X',
					'custom_tracking_link'     => 'javascript:alert(1)',
					'tracking_number'          => 'A',
				],
				false,
			],
		];
	}
}
