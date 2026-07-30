<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Application;

use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Infrastructure\SchemaProvider;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The other half of the pruning bargain.
 *
 * Every generated tool publishes eight to fifteen fields out of the hundred a
 * WooCommerce product declares, because a tools/list carrying all of them would
 * be enormous and would give an agent a hundred ways to get one call wrong.
 * That trade is only honest if the rest stay reachable, and this is how: name a
 * tool, get the full derived schema for the route behind it.
 *
 * The fields it reveals are usable straight away. Write operations publish
 * `additionalProperties: true` precisely so that an agent which has looked one
 * up can send it, and WooCommerce validates it exactly as it validates the
 * curated ones — nothing here grants access to anything, it only describes.
 *
 * It answers about one tool at a time, deliberately: an unfiltered dump of every
 * schema would be exactly the payload the pruning exists to avoid. See
 * required_scope() for how it is gated, and why that choice is arguable.
 */
final readonly class DescribeFieldsTool implements ToolInterface {

	public const NAME = 'describe_woocommerce_fields';

	public function __construct(
		private DescriptorProvider $descriptors,
		private SchemaProvider $schemas,
	) {}

	public function name(): string {
		return self::NAME;
	}

	public function description(): string {
		return 'Show every field WooCommerce accepts for another tool, not just the common ones that tool advertises. Pass the name of any tool you can see. Use it when a field you need is missing from a tool\'s arguments: write tools accept extra fields beyond the ones they list, so a field found here can be sent to that tool directly. Reading this changes nothing.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'tool' => [
					'type'        => 'string',
					'description' => 'The name of the tool to describe, e.g. "create_product".',
				],
			],
			'required'             => [ 'tool' ],
			'additionalProperties' => false,
		];
	}

	/**
	 * Gated as Products read, which is a choice worth being explicit about.
	 *
	 * What this returns is WooCommerce's published API shape — field names,
	 * types and descriptions — not anything from the store, so the risk in
	 * describing a route is close to nil and knowing a schema grants no ability
	 * to call it. There is also no group it naturally belongs to: it is about
	 * the tool surface rather than about any resource.
	 *
	 * So it hangs off the group most likely to be on, which keeps it available
	 * wherever the pruning it compensates for is felt. The consequence to know:
	 * a store that disables Products loses this tool even for other groups, and
	 * a token holding only, say, coupons:read cannot reach it. If that proves
	 * awkward it wants its own group rather than a wider scope here.
	 */
	public function required_scope(): ApiScope {
		return ApiScope::ProductsRead;
	}

	public function group(): ToolGroup {
		return ToolGroup::Products;
	}

	/** Pure description over descriptors already loaded; nothing to probe. */
	public function is_available(): bool {
		return true;
	}

	public function execute( array $arguments ): array {
		$name = trim( (string) ( $arguments['tool'] ?? '' ) );

		[ $resource, $operation ] = $this->find( $name );

		$route  = $resource->route_for( $operation->operation );
		$method = $operation->operation->method();

		$full = $this->schemas->schema(
			self::NAME . ':' . $name,
			$route,
			$method,
			FieldProfile::everything(),
			$route->parameters()
		);

		// Cast: an argument-less route publishes properties as stdClass, and
		// array_keys() fatals on an object.
		$published = array_keys( (array) ( $this->schemas->schema( $name, $route, $method, $operation->fields, $route->parameters() )['properties'] ?? [] ) );
		$all       = array_keys( (array) ( $full['properties'] ?? [] ) );

		return [
			'tool'          => $name,
			'resource'      => $resource->id,
			'method'        => $method->value,
			'route'         => $route->path_template(),
			'published'     => $published,
			'additional'    => array_values( array_diff( $all, $published ) ),
			'accepts_extra' => $operation->fields->allow_additional,
			'schema'        => $full,
		];
	}

	/**
	 * @return array{ResourceDescriptor, OperationDescriptor}
	 * @throws ToolCallException When no such tool exists.
	 */
	private function find( string $name ): array {
		foreach ( $this->descriptors->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				if ( $name === $operation->name->value ) {
					return [ $resource, $operation ];
				}
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
		throw new ToolCallException(
			sprintf(
				'"%s" is not a tool whose fields can be described. Only the WooCommerce resource tools have a schema to show; pass a name exactly as it appears in the tool list.',
				$name
			)
		);
	}
}
