<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * Input schemas already derived this request, keyed by tool name.
 *
 * A tools/list answer touches every visible tool, and the playground renders
 * the same list more than once per page. Deriving twice is pure waste.
 *
 * Keyed by tool name rather than by route, because two operations can share a
 * route and method while surfacing different fields — get_products and a
 * narrowed variant would otherwise hand each other the wrong schema. Tool names
 * are unique by construction; ToolRegistry refuses a collision.
 *
 * Deliberately a mutable object passed in rather than a static: the tools that
 * use it stay final readonly, and a test gets a fresh cache per case for free.
 * Nothing is persisted — derivation is an array_intersect_key over a dozen
 * arguments, which is not worth a transient and its invalidation problem.
 */
final class SchemaCache {

	/** @var array<string, array<string, mixed>> */
	private array $schemas = [];

	/**
	 * @param callable(): array<string, mixed> $build
	 * @return array<string, mixed>
	 */
	public function remember( string $tool, callable $build ): array {
		return $this->schemas[ $tool ] ??= $build();
	}
}
