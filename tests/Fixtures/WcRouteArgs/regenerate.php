<?php
/**
 * Regenerates the checked-in WooCommerce route-argument fixtures.
 *
 * Run against any store with WooCommerce active:
 *
 *     wp eval-file tests/Fixtures/WcRouteArgs/regenerate.php \
 *         "$(pwd)/tests/Fixtures/WcRouteArgs" --path=/path/to/wordpress
 *
 * These fixtures are the schema-drift canary. SchemaFromArgsTest proves the
 * transformation rules against hand-built inputs; the fixtures prove the rules
 * still hold against what WooCommerce actually registers. Regenerate on a
 * WooCommerce major upgrade — a diff here is the early warning that a
 * FieldProfile has gone stale, which is the one failure mode the runtime
 * fallback can only paper over.
 *
 * Not part of the plugin: never loaded at runtime, never shipped.
 *
 * No declare(strict_types=1) here on purpose: wp eval-file runs the file through
 * eval(), where a strict_types declaration is a fatal — "must be the very first
 * statement in the script", and eval'd code is not a script.
 */

/**
 * Route template => HTTP verb, chosen to cover the shapes the derivation has to
 * survive.
 *
 * A variable rather than a const: wp eval-file includes this inside a function,
 * and PHP will not declare a const in function scope.
 *
 * @var array<string, array{string, string}> $counterhand_fixture_routes
 */
$counterhand_fixture_routes = [
	// The resource Phase 5 validates end to end.
	'coupons-collection-get'     => [ '/wc/v3/coupons', 'GET' ],
	'coupons-collection-post'    => [ '/wc/v3/coupons', 'POST' ],
	'coupons-item-put'           => [ '/wc/v3/coupons/{id}', 'PUT' ],
	'coupons-item-delete'        => [ '/wc/v3/coupons/{id}', 'DELETE' ],
	// The ~100-field schema a FieldProfile exists to prune.
	'products-collection-get'    => [ '/wc/v3/products', 'GET' ],
	'products-collection-post'   => [ '/wc/v3/products', 'POST' ],
	// 'type' => 'mixed' at several depths.
	'orders-collection-post'     => [ '/wc/v3/orders', 'POST' ],
	// Nested resource whose collection permission check ignores the parent id.
	'order-notes-collection-get' => [ '/wc/v3/orders/{order_id}/notes', 'GET' ],
	// The customer meta path that MetaKeyPolicy has to defend.
	'customers-collection-post'  => [ '/wc/v3/customers', 'POST' ],
	// Resources with no id-free collection route.
	'system-status-get'          => [ '/wc/v3/system_status', 'GET' ],
	'settings-group-get'         => [ '/wc/v3/settings/{group_id}', 'GET' ],
];

/**
 * Replaces anything var_export() cannot round-trip with a marker string.
 *
 * Closures appear as validate_callback and sanitize_callback on a handful of
 * routes. The schema whitelist drops those keys either way, so a marker keeps
 * the fixture honest about the key being present without pretending to
 * serialize the callable.
 */
function counterhand_exportable( mixed $value ): mixed {
	if ( is_array( $value ) ) {
		return array_map( 'counterhand_exportable', $value );
	}

	if ( $value instanceof Closure ) {
		return '__closure__';
	}

	if ( is_object( $value ) ) {
		return '__object:' . get_class( $value ) . '__';
	}

	if ( ! is_string( $value ) && is_callable( $value ) ) {
		return '__callable__';
	}

	return $value;
}

$out_dir = $args[0] ?? null;

if ( ! is_string( $out_dir ) || ! is_dir( $out_dir ) ) {
	WP_CLI::error( 'Pass the fixture directory as the first positional argument.' );
}

// Same normalization the plugin's RouteIndexer applies, so the templates above
// are spelled the way descriptors spell them.
$index = [];

foreach ( rest_get_server()->get_routes() as $pattern => $handlers ) {
	$template = preg_replace( '#\(\?P<(\w+?)>.*?\)#', '{$1}', (string) $pattern );

	foreach ( (array) $handlers as $handler ) {
		if ( ! is_array( $handler ) || ! isset( $handler['methods'] ) || ! is_array( $handler['methods'] ) ) {
			continue;
		}

		foreach ( array_keys( $handler['methods'] ) as $verb ) {
			$index[ $template ][ $verb ] ??= $handler['args'] ?? [];
		}
	}
}

$wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown';
$wp_version = get_bloginfo( 'version' );

foreach ( $counterhand_fixture_routes as $slug => [ $template, $verb ] ) {
	if ( ! isset( $index[ $template ][ $verb ] ) ) {
		WP_CLI::warning( sprintf( 'Not registered on this store: %s %s', $verb, $template ) );
		continue;
	}

	$route_args = counterhand_exportable( $index[ $template ][ $verb ] );
	$export     = preg_replace( '/^(\s*)array \(/m', '$1array(', var_export( $route_args, true ) );

	$contents = <<<PHP
<?php
/**
 * Arguments WooCommerce registers for {$verb} {$template}.
 *
 * Captured from WooCommerce {$wc_version} on WordPress {$wp_version}.
 * Callables are replaced with marker strings; the schema whitelist drops those
 * keys either way.
 *
 * Generated — do not hand-edit. See regenerate.php in this directory.
 */

return {$export};

PHP;

	file_put_contents( rtrim( $out_dir, '/' ) . '/' . $slug . '.php', $contents );

	WP_CLI::log( sprintf( 'Wrote %s.php (%d arguments)', $slug, count( $route_args ) ) );
}
