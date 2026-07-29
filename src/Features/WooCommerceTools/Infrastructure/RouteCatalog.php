<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * The REST routes this site actually serves, looked up by RestRoute + RestMethod.
 *
 * A route absent from here is a route this WooCommerce does not have — which is
 * the signal needed to hide the corresponding tool rather than advertise a
 * schema for an endpoint that would 404.
 *
 * Deliberately thin: fetching the route table and remembering it for the
 * request. The translation from WordPress' shape into ours lives in
 * RouteIndexer, where it can be tested without WordPress present.
 *
 * Not readonly — the index is memoized. get_routes() walks every registered
 * endpoint on the site, so it is worth paying for exactly once.
 */
final class RouteCatalog {

	/** @var array<string, array<string, RouteArgs>>|null Template => method => args. */
	private ?array $index = null;

	public function __construct(
		private readonly RouteIndexer $indexer = new RouteIndexer(),
	) {}

	public function find( RestRoute $route, RestMethod $method ): ?RouteArgs {
		return $this->index()[ $route->path_template() ][ $method->value ] ?? null;
	}

	public function has( RestRoute $route, RestMethod $method ): bool {
		return null !== $this->find( $route, $method );
	}

	/** @return array<string, array<string, RouteArgs>> */
	private function index(): array {
		if ( null !== $this->index ) {
			return $this->index;
		}

		/*
		 * rest_get_server() fires rest_api_init, which WooCommerce answers by
		 * registering controllers for post types that only exist once init has
		 * run. Forcing it early would build a catalog missing half the store —
		 * and memoizing that emptiness would make the damage permanent, so bail
		 * without caching.
		 *
		 * did_action() counts from the moment do_action() starts, so this is
		 * already true for a callback running on init itself. Every path that
		 * reaches a tool's input schema (parse_request, rest_api_init,
		 * wp_ajax_*) is later still.
		 */
		if ( ! did_action( 'init' ) ) {
			return [];
		}

		$this->index = $this->indexer->index( rest_get_server()->get_routes() );

		return $this->index;
	}
}
