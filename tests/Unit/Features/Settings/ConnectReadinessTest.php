<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Settings;

use Counterhand\Features\Settings\ConnectReadiness;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The host classification is the part worth pinning down: it decides whether
 * the Connect tab tells the admin that cloud assistants can reach their store,
 * and getting it wrong either blocks a working store or sends someone off to
 * claude.ai to fail.
 */
final class ConnectReadinessTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// wp_parse_url is a thin wrapper over parse_url for the URLs used here.
		Functions\when( 'wp_parse_url' )->alias(
			static fn ( string $url, int $component = -1 ) => parse_url( $url, $component )
		);
	}

	/** @dataProvider unreachable_urls */
	public function test_unreachable_addresses_are_rejected( string $url ): void {
		self::assertNotNull( ConnectReadiness::public_reachability_problem( $url ) );
	}

	public static function unreachable_urls(): array {
		return [
			'plain http'          => [ 'http://shop.example.com' ],
			'localhost'           => [ 'https://localhost' ],
			'loopback ip'         => [ 'https://127.0.0.1' ],
			'ipv6 loopback'       => [ 'https://::1' ],
			'.local suffix'       => [ 'https://shop.local' ],
			'.test suffix'        => [ 'https://shop.test' ],
			'private ip'          => [ 'https://192.168.1.10' ],
			'private ip 10.x'     => [ 'https://10.0.0.5' ],
			'hostname, no domain' => [ 'https://intranet' ],
			'no host at all'      => [ 'not-a-url' ],
		];
	}

	/** @dataProvider reachable_urls */
	public function test_public_https_addresses_pass( string $url ): void {
		self::assertNull( ConnectReadiness::public_reachability_problem( $url ) );
	}

	public static function reachable_urls(): array {
		return [
			'apex domain' => [ 'https://hygienemitsystem.at' ],
			'subdomain'   => [ 'https://shop.example.co.uk' ],
			'with a path' => [ 'https://example.com/store' ],
			'public ipv4' => [ 'https://93.184.216.34' ],
			'with a port' => [ 'https://example.com:8443' ],
		];
	}
}
