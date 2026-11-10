<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Settings;

use Brain\Monkey\Functions;
use Counterhand\Features\Settings\McpClient;
use Counterhand\Tests\Unit\TestCase;

/** The name a client files this store under in its own config. */
final class McpClientServerSlugTest extends TestCase {

	private function slug_for( string $home ): string {
		Functions\when( 'home_url' )->justReturn( $home );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) );

		return McpClient::server_slug();
	}

	public function test_the_store_is_named_by_its_host(): void {
		$this->assertSame( 'hygienemitsystem-at', $this->slug_for( 'https://www.hygienemitsystem.at' ) );
		$this->assertSame( 'shop-example-com-de', $this->slug_for( 'https://shop.example.com/de' ) );
		$this->assertSame( 'localhost-8007', $this->slug_for( 'http://localhost:8007' ) );
		$this->assertSame( '1-8080', $this->slug_for( 'http://[::1]:8080' ) );
	}

	public function test_long_addresses_that_differ_at_the_end_stay_apart(): void {
		$german  = $this->slug_for( 'https://shop.example-company-holding-group.co.uk/de' );
		$english = $this->slug_for( 'https://shop.example-company-holding-group.co.uk/en' );

		$this->assertNotSame( $german, $english );
		$this->assertLessThanOrEqual( 48, strlen( $german ) );
	}

	public function test_a_store_with_no_usable_host_still_gets_a_name(): void {
		$this->assertSame( 'woocommerce', $this->slug_for( '' ) );
	}
}
