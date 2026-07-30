<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * What WooCommerce registered for one route + method: its argument specs and
 * the permission callback it wants consulted.
 *
 * This is the seam the whole tool surface hangs off. Every wc/v3 controller
 * derives its per-method args from its own item schema — collection params for
 * GET, get_endpoint_args_for_item_schema() for POST and PUT — so reading them
 * back out is how a tool's input schema tracks WooCommerce instead of restating
 * it. A field WooCommerce adds appears here on the next request; a field it
 * removes disappears without anything to update.
 */
final readonly class RouteArgs {

	/**
	 * @param string                    $template            Normalized path, e.g. "/wc/v3/products/{id}".
	 * @param array<string, mixed>      $args                WordPress argument specs, keyed by argument name.
	 * @param callable|null             $permission_callback What the router would call before dispatching.
	 */
	public function __construct(
		public string $template,
		public RestMethod $method,
		public array $args,
		public mixed $permission_callback = null,
	) {}

	/**
	 * Argument names as WooCommerce declares them.
	 *
	 * @return list<string>
	 */
	public function names(): array {
		return array_map( 'strval', array_keys( $this->args ) );
	}

	/**
	 * A route with no declared args publishes an empty input schema rather than
	 * a stale one — worth distinguishing from "route absent", which hides the
	 * tool entirely.
	 */
	public function declares_args(): bool {
		return [] !== $this->args;
	}

	/**
	 * The default value of every argument that declares one.
	 *
	 * WP_REST_Server collects exactly this before it calls a permission callback
	 * (class-wp-rest-server.php, match_request_to_handler()) and hands it to
	 * WP_REST_Request::set_default_params(), which is the only reason
	 * $request['context'] reads as 'view' inside a permission check. Defaults do
	 * not travel with the attributes, so anything building a request outside the
	 * router has to gather them the same way.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		$defaults = [];

		foreach ( $this->args as $name => $spec ) {
			if ( ! is_array( $spec ) || ! isset( $spec['default'] ) ) {
				continue;
			}

			$defaults[ (string) $name ] = $spec['default'];
		}

		return $defaults;
	}
}
