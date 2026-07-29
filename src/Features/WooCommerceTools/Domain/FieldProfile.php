<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Which of a route's fields an operation surfaces, by name only.
 *
 * WooCommerce declares roughly a hundred fields on a product. Publishing all of
 * them in every tool's input schema would make tools/list enormous and give an
 * agent a hundred ways to get one call wrong. A profile names the eight to
 * fifteen that matter for one operation.
 *
 * Names only, deliberately: types, enums, defaults and descriptions still come
 * from WooCommerce at runtime. A profile that also carried types would be a
 * second copy of the schema, and copies rot — which is the thing this whole
 * design exists to avoid. The most a stale profile can do is name a field that
 * no longer exists, which costs nothing.
 */
final readonly class FieldProfile {

	/**
	 * @param list<string> $input            Argument names to surface in the tool's input schema.
	 * @param list<string> $output           Field names to request back, via the REST _fields param.
	 * @param bool         $allow_additional Whether the input schema accepts fields it does not name.
	 */
	public function __construct(
		public array $input,
		public array $output,
		public bool $allow_additional = true,
	) {}

	/**
	 * No pruning on either side.
	 *
	 * Also the landing place for a profile discovered to be stale: a fat schema
	 * is worse than a curated one, but far better than an empty one.
	 */
	public static function everything(): self {
		return new self( [], [] );
	}

	public function prunes_input(): bool {
		return [] !== $this->input;
	}

	/**
	 * The subset of $args this profile admits, in WooCommerce's own order.
	 *
	 * Ordering by WooCommerce rather than by the profile means a descriptor
	 * edit cannot reshuffle a published schema, and id-first stays id-first.
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public function select( array $args ): array {
		if ( ! $this->prunes_input() ) {
			return $args;
		}

		return array_intersect_key( $args, array_flip( $this->input ) );
	}

	/**
	 * True when the profile names nothing the route still declares.
	 *
	 * That is the signature of a descriptor left behind by a WooCommerce
	 * release — a renamed resource, a rebuilt controller — as opposed to the
	 * ordinary case of one or two fields having gone. Callers fall back to
	 * everything() rather than publish a tool with no arguments at all.
	 *
	 * @param array<string, mixed> $args
	 */
	public function is_stale_against( array $args ): bool {
		return [] !== $args && [] === $this->select( $args );
	}

	/**
	 * The value for the REST _fields parameter, or null to ask for everything.
	 */
	public function output_fields(): ?string {
		if ( [] === $this->output ) {
			return null;
		}

		return implode( ',', $this->output );
	}
}
