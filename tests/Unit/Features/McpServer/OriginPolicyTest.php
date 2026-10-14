<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\McpServer;

use Brain\Monkey\Functions;
use Counterhand\Features\McpServer\OriginPolicy;
use Counterhand\Tests\Unit\TestCase;

final class OriginPolicyTest extends TestCase {

	/** @var list<string> */
	private array $extra = [];

	protected function setUp(): void {
		parent::setUp();

		$this->extra = [];
		Functions\when( 'home_url' )->justReturn( 'https://shop.example.com' );
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url, int $component = -1 ) => parse_url( $url, $component ) );
		Functions\when( 'apply_filters' )->alias( fn ( string $hook, $value ) => 'counterhand_mcp_allowed_origins' === $hook ? $this->extra : $value );
	}

	/** @dataProvider accepted */
	public function test_accepts( string $origin ): void {
		$this->assertTrue( ( new OriginPolicy() )->allows( $origin ) );
	}

	/** @return \Generator<string, array{string}> */
	public static function accepted(): \Generator {
		yield 'the store itself'              => [ 'https://shop.example.com' ];
		yield 'the store, explicit port 443'  => [ 'https://shop.example.com:443' ];
		yield 'the store, any case'           => [ 'HTTPS://Shop.Example.com' ];
		yield 'localhost, any port'           => [ 'http://localhost:6274' ];
		yield 'IPv4 loopback'                 => [ 'http://127.0.0.1:3000' ];
		yield 'IPv6 loopback'                 => [ 'http://[::1]:8080' ];
	}

	/** @dataProvider refused */
	public function test_refuses( string $origin ): void {
		$this->assertFalse( ( new OriginPolicy() )->allows( $origin ) );
	}

	/** @return \Generator<string, array{string}> */
	public static function refused(): \Generator {
		yield 'a rebinding page'                    => [ 'http://evil.example.com' ];
		yield 'the store over plain http'           => [ 'http://shop.example.com' ];
		yield 'the store on another port'           => [ 'https://shop.example.com:8443' ];
		yield 'a lookalike subdomain'               => [ 'https://shop.example.com.evil.test' ];
		yield 'a sandboxed frame'                   => [ 'null' ];
		yield 'a non-http scheme'                   => [ 'file://localhost' ];
	}

	public function test_a_filtered_origin_is_accepted(): void {
		$this->extra = [ 'https://app.example.org/' ];

		$this->assertTrue( ( new OriginPolicy() )->allows( 'https://app.example.org' ) );
	}
}
