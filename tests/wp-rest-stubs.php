<?php
/**
 * A stand-in for WP_REST_Request, so the unit suite can exercise code that has
 * to hand one to WordPress.
 *
 * Unlike wp-schema-stubs.php this is NOT a copy of core. WP_REST_Request is
 * ~1000 lines of parameter precedence, header canonicalization and validation,
 * none of which RoutePermissionProbe touches: it builds a request, attaches the
 * route's args and defaults, and passes it to a callback that the test provides.
 * So this implements that surface and nothing else.
 *
 * The one behavioural difference worth naming: core resolves get_param() across
 * five sources in an order that depends on the request method, while this
 * resolves defaults only. A test that needed the real precedence would be
 * testing WordPress, not this plugin.
 *
 * Guarded by class_exists() so it stays inert if the suite is ever run with
 * WordPress loaded.
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_REST_Request' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- deliberately shadowing a WordPress class name in tests.
	class WP_REST_Request implements ArrayAccess {

		/** @var array<string, mixed> */
		private array $defaults = [];

		/** @param array<string, mixed> $attributes */
		public function __construct(
			private string $method = '',
			private string $route = '',
			private array $attributes = []
		) {}

		public function get_method(): string {
			return $this->method;
		}

		public function get_route(): string {
			return $this->route;
		}

		/** @return array<string, mixed> */
		public function get_attributes(): array {
			return $this->attributes;
		}

		/** @param array<string, mixed> $attributes */
		public function set_attributes( array $attributes ): void {
			$this->attributes = $attributes;
		}

		/** @param array<string, mixed> $params */
		public function set_default_params( array $params ): void {
			$this->defaults = $params;
		}

		/** @return array<string, mixed> */
		public function get_default_params(): array {
			return $this->defaults;
		}

		public function get_param( string $key ): mixed {
			return $this->defaults[ $key ] ?? null;
		}

		public function offsetExists( mixed $offset ): bool {
			return isset( $this->defaults[ $offset ] );
		}

		public function offsetGet( mixed $offset ): mixed {
			return $this->get_param( (string) $offset );
		}

		public function offsetSet( mixed $offset, mixed $value ): void {
			$this->defaults[ (string) $offset ] = $value;
		}

		public function offsetUnset( mixed $offset ): void {
			unset( $this->defaults[ $offset ] );
		}
	}
}
