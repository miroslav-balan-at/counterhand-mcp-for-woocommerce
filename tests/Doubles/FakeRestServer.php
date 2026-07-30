<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Doubles;

/**
 * Stands in for WP_REST_Server so RouteCatalog can be pointed at a route table
 * the test wrote, in the raw shape get_routes() returns: regex keys, a list of
 * handlers per route, methods as a name => true map.
 */
final class FakeRestServer {

	/** @var array<string, mixed> */
	private array $routes;

	public int $get_routes_calls = 0;

	/** @param array<string, mixed> $routes */
	public function __construct( array $routes = [] ) {
		$this->routes = $routes;
	}

	/**
	 * One route, one method, one permission callback — the shape most of these
	 * tests want, without restating the nesting each time.
	 *
	 * @param array<string, mixed> $args
	 */
	public static function serving( string $pattern, string $method, ?callable $permission_callback, array $args = [] ): self {
		return new self(
			[
				$pattern => [
					[
						'methods'             => [ $method => true ],
						'args'                => $args,
						'permission_callback' => $permission_callback,
					],
				],
			]
		);
	}

	/** @return array<string, mixed> */
	public function get_routes(): array {
		++$this->get_routes_calls;

		return $this->routes;
	}
}
