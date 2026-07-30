<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;

defined( 'ABSPATH' ) || exit;

/**
 * Turns what WooCommerce registered for a route into the JSON Schema an MCP
 * client is handed.
 *
 * The two formats are almost the same on purpose — WordPress models argument
 * specs on JSON Schema — so this is mostly subtraction, and the subtraction is
 * delegated to core's own whitelist. Three differences are real:
 *
 * 1. WordPress carries "required" as a sibling flag per argument; JSON Schema
 *    wants one list of names on the object.
 * 2. WooCommerce uses 'type' => 'mixed', which is not a JSON Schema type.
 * 3. The REST envelope params (context, _fields, …) belong to the transport,
 *    not to the tool, and are forced at dispatch instead.
 *
 * Everything else — descriptions, enums, formats, defaults, nested object
 * shapes — is passed through untouched, which is the point: it stays whatever
 * WooCommerce says it is.
 */
final readonly class SchemaFromArgs {

	/** The only types JSON Schema defines. Anything else is dropped, not guessed at. */
	private const JSON_SCHEMA_TYPES = [ 'array', 'boolean', 'integer', 'null', 'number', 'object', 'string' ];

	/** Well past anything wc/v3 declares; the ceiling is for third-party args. */
	private const MAX_SCHEMA_DEPTH = 20;

	/**
	 * Params the REST envelope owns.
	 *
	 * Exposing these would let an agent ask for 'context' => 'edit', which
	 * returns raw HTML and roughly doubles every payload, or set _fields and
	 * silently defeat the response pruning the tool already does for it.
	 */
	private const ENVELOPE_PARAMS = [ 'context', '_fields', '_embed', '_links', '_method', '_envelope', '_jsonp', '_locale' ];

	/**
	 * All WooCommerce says about a path parameter it does not describe.
	 *
	 * Reached only if a route template still has a placeholder the controller
	 * stopped declaring. A string is the least this can claim and stay true —
	 * the value goes into a URL either way, and WooCommerce's own route regex
	 * rejects anything the endpoint cannot take.
	 */
	private const UNDECLARED_PATH_PARAM = [
		'type'        => 'string',
		'description' => 'Identifies the resource in the request path. WooCommerce publishes no description for it.',
	];

	/**
	 * @param  list<string>         $path_params Placeholder names the route cannot be built without.
	 * @return array<string, mixed>              A JSON Schema object describing the tool's input.
	 */
	public function build( RouteArgs $route_args, FieldProfile $fields, array $path_params = [] ): array {
		$args       = $this->admitted( $this->without_envelope( $route_args->args ), $fields, $route_args, $path_params );
		$properties = [];
		$required   = [];

		foreach ( $args as $name => $spec ) {
			// A handful of legacy routes register an argument as a bare string
			// rather than a spec. There is no schema in that to publish.
			if ( ! is_array( $spec ) ) {
				continue;
			}

			$properties[ (string) $name ] = $this->schema( $spec );

			if ( $this->is_required( $spec ) ) {
				$required[] = (string) $name;
			}
		}

		/*
		 * WooCommerce marks none of these required, and is right not to: it
		 * reads them out of the URL, where they are structural rather than
		 * optional. Coming the other way, an agent has only the argument list to
		 * go on, so omitting the id has to be a schema violation it can see
		 * rather than a dispatch failure it cannot.
		 */
		foreach ( $path_params as $name ) {
			$properties[ $name ] ??= self::UNDECLARED_PATH_PARAM;
			$required[]            = $name;
		}

		$schema = [
			'type'       => 'object',
			// An empty PHP array encodes as [], and a provider rejects the whole
			// request over it: "input_schema.properties: Input should be an object".
			'properties' => [] !== $properties ? $properties : new \stdClass(),
		];

		if ( [] !== $required ) {
			$schema['required'] = array_values( array_unique( $required ) );
		}

		$schema['additionalProperties'] = $fields->allow_additional;

		return $schema;
	}

	/**
	 * @param  array<string, mixed> $args
	 * @param  list<string>         $path_params
	 * @return array<string, mixed>
	 */
	private function admitted( array $args, FieldProfile $fields, RouteArgs $route_args, array $path_params ): array {
		if ( $fields->is_stale_against( $args ) ) {
			$this->warn_stale( $route_args );

			return $args;
		}

		// WooCommerce rejects a call missing a required argument, so a profile
		// that forgot one would ship a tool no agent can ever call
		// successfully. Required arguments are not the descriptor's to prune,
		// and neither is anything the route is addressed by.
		return $fields->select( $args )
			+ $this->required_args( $args )
			+ array_intersect_key( $args, array_flip( $path_params ) );
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function required_args( array $args ): array {
		return array_filter(
			$args,
			fn ( mixed $spec ): bool => is_array( $spec ) && $this->is_required( $spec )
		);
	}

	/**
	 * Whether an argument spec carries the "required" flag.
	 *
	 * The same key means two different things at two different depths. On an
	 * argument WordPress writes `'required' => true`; on a nested object schema
	 * it is JSON Schema's list of required property names. Reading the list as
	 * the flag would mark the whole argument required — for orders that would
	 * make every write fail until the agent sent a billing address.
	 *
	 * @param array<string, mixed> $spec
	 */
	private function is_required( array $spec ): bool {
		$required = $spec['required'] ?? false;

		return ! is_array( $required ) && (bool) $required;
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function without_envelope( array $args ): array {
		return array_diff_key( $args, array_flip( self::ENVELOPE_PARAMS ) );
	}

	/**
	 * One argument spec as JSON Schema.
	 *
	 * @param array<string, mixed> $spec
	 * @return array<string, mixed>
	 */
	private function schema( array $spec, int $depth = 0 ): array {
		// Core's own list, so validate_callback, sanitize_callback and
		// arg_options fall away here, and a keyword WordPress adds later
		// survives with no edit. This is the same call get_data_for_route()
		// makes when core publishes a route's own schema.
		$schema = array_intersect_key( $spec, array_flip( rest_get_allowed_schema_keywords() ) );
		$schema = $this->normalize_type( $schema );

		// A route arg is third-party data: an extension is free to register a
		// spec that references itself, and recursing into one would exhaust the
		// stack rather than fail a tool. Stopping leaves the branch untyped,
		// which is what an agent can still work with.
		if ( $depth >= self::MAX_SCHEMA_DEPTH ) {
			return $schema;
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$schema['items'] = $this->schema( $schema['items'], $depth + 1 );
		}

		if ( isset( $schema['additionalProperties'] ) && is_array( $schema['additionalProperties'] ) ) {
			$schema['additionalProperties'] = $this->schema( $schema['additionalProperties'], $depth + 1 );
		}

		foreach ( [ 'properties', 'patternProperties', 'anyOf', 'oneOf' ] as $keyword ) {
			if ( ! isset( $schema[ $keyword ] ) || ! is_array( $schema[ $keyword ] ) ) {
				continue;
			}

			$schema[ $keyword ] = $this->map_schemas( $schema[ $keyword ], $depth + 1 );
		}

		/*
		 * "required" is not in core's whitelist, so the intersect above just
		 * dropped it. At the top level that is right — it is a bool there, and
		 * build() has already hoisted it into the object's required list. On a
		 * nested object it is already a list of names, which is valid JSON
		 * Schema and worth keeping rather than losing to a shape mismatch.
		 */
		if ( isset( $spec['required'] ) && is_array( $spec['required'] ) ) {
			$schema['required'] = array_values( array_filter( $spec['required'], 'is_string' ) );
		}

		return $schema;
	}

	/**
	 * @param array<array-key, mixed> $schemas
	 * @return array<array-key, mixed>
	 */
	private function map_schemas( array $schemas, int $depth = 0 ): array {
		return array_map(
			fn ( mixed $schema ): mixed => is_array( $schema ) ? $this->schema( $schema, $depth ) : $schema,
			$schemas
		);
	}

	/**
	 * @param array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	private function normalize_type( array $schema ): array {
		if ( ! isset( $schema['type'] ) ) {
			return $schema;
		}

		$types = array_values(
			array_intersect(
				array_map( 'strval', (array) $schema['type'] ),
				self::JSON_SCHEMA_TYPES
			)
		);

		if ( [] === $types ) {
			/*
			 * WooCommerce declares 'type' => 'mixed' on meta_data values and
			 * several order and refund fields. It is not a JSON Schema type:
			 * strict MCP clients reject a schema containing it outright, and
			 * rest_validate_value_from_schema() _doing_it_wrong()s on it.
			 * Dropping the keyword leaves the description, which is the part
			 * an agent can actually act on, and leaves the value unconstrained
			 * — which is what "mixed" meant anyway.
			 */
			unset( $schema['type'] );

			return $schema;
		}

		$schema['type'] = 1 === count( $types ) ? $types[0] : $types;

		return $schema;
	}

	/**
	 * A profile that matches nothing usually means WooCommerce rebuilt the
	 * controller. Worth saying out loud to a developer, worth saying nothing at
	 * all to a store owner — the tool still works, just with a fat schema.
	 */
	private function warn_stale( RouteArgs $route_args ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		wc_get_logger()->warning(
			sprintf(
				'Field profile for %s %s matches none of the arguments WooCommerce declares; falling back to the full schema.',
				$route_args->method->value,
				$route_args->template
			),
			[ 'source' => 'counterhand' ]
		);
	}
}
