<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\McpServer;

use Brain\Monkey\Functions;
use Counterhand\Features\McpServer\McpServerFeature;
use Counterhand\Features\Playground\PlaygroundFeature;
use Counterhand\Tests\Unit\TestCase;

/** WPML's specs cannot run in CI, so which requests count is pinned here too. */
final class ServesToolsHereTest extends TestCase {

	protected function tearDown(): void {
		unset( $_SERVER['REQUEST_URI'], $_GET['rest_route'], $_REQUEST['action'] );
		parent::tearDown();
	}

	/**
	 * @dataProvider requests
	 * @param array<string, string> $query
	 */
	public function test_the_endpoint_answers_for_its_own_urls( string $home, string $uri, array $query, bool $serves ): void {
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => $home . $path );
		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
		Functions\when( 'untrailingslashit' )->alias( static fn ( string $value ): string => rtrim( $value, '/\\' ) );
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url, int $component = -1 ): mixed => parse_url( $url, $component ) );

		$_SERVER['REQUEST_URI'] = $uri;
		$_GET                   = $query;

		$feature = ( new \ReflectionClass( McpServerFeature::class ) )->newInstanceWithoutConstructor();

		$this->assertSame( $serves, $feature->serves_tools_here( false ) );
		$this->assertTrue( $feature->serves_tools_here( true ), 'another answer stands' );
	}

	/** @return array<string, array{string, string, array<string, string>, bool}> */
	public static function requests(): array {
		return [
			'the endpoint'             => [ 'https://shop.test', '/mcp', [], true ],
			'with a slash and a query' => [ 'https://shop.test', '/mcp/?x=1', [], true ],
			'in a subfolder'           => [ 'https://shop.test/store', '/store/mcp', [], true ],
			'the REST fallback'        => [ 'https://shop.test', '/wp-json/counterhand/v1/mcp', [], true ],
			'plain permalinks'         => [ 'https://shop.test', '/index.php', [ 'rest_route' => '/counterhand/v1/mcp' ], true ],
			'a path merely ending so'  => [ 'https://shop.test', '/x/wp-json/counterhand/v1/mcp', [], false ],
			'a page'                   => [ 'https://shop.test', '/shop/', [], false ],
			'a page ending in mcp'     => [ 'https://shop.test', '/about-mcp', [], false ],
			'another REST route'       => [ 'https://shop.test', '/wp-json/wc/v3/products', [], false ],
		];
	}

	public function test_the_chat_answers_for_its_own_actions_only(): void {
		Functions\when( 'wp_doing_ajax' )->justReturn( true );
		$feature = ( new \ReflectionClass( PlaygroundFeature::class ) )->newInstanceWithoutConstructor();

		foreach ( [
			'counterhand_chat_send'   => true,
			'counterhand_chat_resume' => true,
			'heartbeat'               => false,
		] as $action => $serves ) {
			$_REQUEST['action'] = $action;
			$this->assertSame( $serves, $feature->serves_tools_here( false ), $action );
		}

		unset( $_REQUEST['action'] );
		$this->assertFalse( $feature->serves_tools_here( false ) );
	}
}
