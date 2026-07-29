<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Infrastructure;

use AgentGateMcp\Features\WooCommerceTools\Domain\FieldProfile;

defined( 'ABSPATH' ) || exit;

/**
 * The input schema for one tool, derived from the live route table.
 *
 * Three collaborators have to be walked in order to answer that — find the
 * route's registered args, translate them, remember the result — and every tool
 * would otherwise walk them identically. Composing them once here is what lets
 * a tool hold a single collaborator for its schema, and gives the derivation a
 * place to state its own edge case: a route this WooCommerce does not serve
 * publishes an empty schema rather than a fatal, because the tool is on its way
 * to being hidden anyway and a half-built schema helps nobody.
 */
final readonly class SchemaProvider {

	public function __construct(
		private RouteCatalog $catalog,
		private SchemaFromArgs $builder = new SchemaFromArgs(),
		private SchemaCache $cache = new SchemaCache(),
	) {}

	/**
	 * @param  string               $tool        Cache key; tool names are unique by construction.
	 * @param  list<string>         $path_params Placeholders the route cannot be built without.
	 * @return array<string, mixed>
	 */
	public function schema( string $tool, RestRoute $route, RestMethod $method, FieldProfile $fields, array $path_params = [] ): array {
		return $this->cache->remember(
			$tool,
			function () use ( $route, $method, $fields, $path_params ): array {
				$route_args = $this->catalog->find( $route, $method );

				if ( null === $route_args ) {
					return $this->empty_schema();
				}

				return $this->builder->build( $route_args, $fields, $path_params );
			}
		);
	}

	/**
	 * MCP requires an object schema, and an object with no properties is the
	 * honest statement that we could not find out what this route takes.
	 *
	 * @return array<string, mixed>
	 */
	private function empty_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [],
			'additionalProperties' => false,
		];
	}
}
