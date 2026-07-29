<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * Turns WordPress' route table into something a descriptor can look itself up in.
 *
 * Registered routes are regexes ("/wc/v3/products/(?P<id>[\d]+)") keyed to a
 * list of handlers, each covering one or more HTTP methods. Descriptors declare
 * templates ("/wc/v3/products/{id}") and one method. This is the translation
 * between the two, and it is pure — no WordPress function is called here, which
 * is what lets the whole of it be tested against a literal route table.
 */
final readonly class RouteIndexer {

	/**
	 * Core's own route normalization, lifted verbatim from
	 * WP_REST_Server::get_data_for_route(). Copying the expression rather than
	 * approximating it is what guarantees a descriptor's "{id}" and the
	 * router's "(?P<id>[\d]+)" always agree on how a placeholder is spelled.
	 */
	private const PLACEHOLDER_PATTERN = '#\(\?P<(\w+?)>.*?\)#';

	/**
	 * @param array<array-key, mixed> $routes As returned by WP_REST_Server::get_routes().
	 * @return array<string, array<string, RouteArgs>> Template => method => args.
	 */
	public function index( array $routes ): array {
		$index = [];

		foreach ( $routes as $pattern => $handlers ) {
			if ( ! is_array( $handlers ) ) {
				continue;
			}

			$template = (string) preg_replace( self::PLACEHOLDER_PATTERN, '{$1}', (string) $pattern );

			foreach ( $handlers as $handler ) {
				if ( ! is_array( $handler ) ) {
					continue;
				}

				$index = $this->add_handler( $index, $template, $handler );
			}
		}

		return $index;
	}

	/**
	 * @param array<string, array<string, RouteArgs>> $index
	 * @param array<array-key, mixed>                 $handler
	 * @return array<string, array<string, RouteArgs>>
	 */
	private function add_handler( array $index, string $template, array $handler ): array {
		$args     = isset( $handler['args'] ) && is_array( $handler['args'] ) ? $handler['args'] : [];
		$callback = $handler['permission_callback'] ?? null;

		foreach ( $this->methods_of( $handler ) as $method ) {
			// The router dispatches to the first handler that matches a method,
			// so a later registration for the same method is unreachable. It
			// must not be the one whose schema and permission check we publish.
			$index[ $template ][ $method->value ] ??= new RouteArgs(
				$template,
				$method,
				$args,
				is_callable( $callback ) ? $callback : null
			);
		}

		return $index;
	}

	/**
	 * @param array<array-key, mixed> $handler
	 * @return list<RestMethod>
	 */
	private function methods_of( array $handler ): array {
		if ( ! isset( $handler['methods'] ) || ! is_array( $handler['methods'] ) ) {
			return [];
		}

		// get_routes() normalizes methods to a "GET" => true map, and registers
		// EDITABLE as POST, PUT and PATCH together. Verbs outside the four this
		// plugin dispatches (PATCH, OPTIONS) are simply not ours to index.
		return array_values(
			array_filter(
				array_map(
					static fn ( string|int $verb ): ?RestMethod => RestMethod::tryFrom( (string) $verb ),
					array_keys( $handler['methods'] )
				)
			)
		);
	}
}
