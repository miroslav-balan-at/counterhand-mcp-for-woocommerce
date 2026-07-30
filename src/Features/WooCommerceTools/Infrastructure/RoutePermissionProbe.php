<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * Asks WooCommerce whether the current user may use a route, by running the
 * permission callback WooCommerce itself registered on it.
 *
 * The alternative was a capability map in this plugin, and WooCommerce's own
 * rules are too varied for one to survive: wc_rest_check_post_permissions()
 * reads the post type object's cap set, wc_rest_check_user_permissions() has a
 * shop_manager branch, wc_rest_check_manager_permissions() maps seven objects
 * onto four capabilities, and product reviews want moderate_comments to read
 * but edit_products to write. All of it is filterable by third parties. A map
 * here would be both a reimplementation and a permanent source of drift.
 *
 * Two things this deliberately does not do:
 *
 * - It probes collection routes, never item routes. map_meta_cap() emits
 *   _doing_it_wrong() and returns do_not_allow for 'edit_post' asked without a
 *   post id, so probing /products/{id} denies everyone, administrators
 *   included. Collection checks are id-independent even for nested resources.
 * - It does not authorize. The answer decides whether a tool is offered;
 *   rest_do_request() then runs the real, id-aware check on every dispatch.
 *   Nothing executable escapes WooCommerce's own gate by being visible here.
 *
 * Not readonly: answers are memoized per request. Two probes per resource,
 * each usually a single current_user_can(), is cheaper than one get_posts().
 */
final class RoutePermissionProbe {

	/** @var array<string, bool> */
	private array $answers = [];

	public function __construct( private readonly RouteCatalog $catalog ) {}

	public function allows( RestRoute $route, RestMethod $method ): bool {
		return $this->answers[ $method->value . ' ' . $route->path_template() ] ??= $this->ask( $route, $method );
	}

	private function ask( RestRoute $route, RestMethod $method ): bool {
		$route_args = $this->catalog->find( $route, $method );
		$callback   = $route_args?->permission_callback;

		// Either this WooCommerce does not serve the route, or it serves it
		// with nothing to consult. Neither is evidence that the user may use
		// it, and absent evidence the answer is no.
		if ( null === $route_args || ! is_callable( $callback ) ) {
			return false;
		}

		// Permission callbacks read parameters — WP_REST_Posts_Controller branches
		// on $request['context'] — and the router populates those defaults itself
		// just before it calls the callback. Skipping that step would show the
		// callback a null where WooCommerce guarantees it a value, so the two
		// calls the router makes are made here too, in the same order.
		$request = new \WP_REST_Request( $method->value, $route->path_template(), [ 'args' => $route_args->args ] );
		$request->set_default_params( $route_args->defaults() );

		// Callbacks return true, false, or a WP_Error carrying the reason.
		// Only the first of those is permission.
		return true === $callback( $request );
	}
}
