<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Domain;

use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestRoute;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * One wc/v3 resource and the tools it offers.
 *
 * This is the whole per-resource knowledge the plugin holds: two routes, two
 * nouns, and a list of operations. Everything an agent actually sees — field
 * names, types, enums, defaults, descriptions of fields — is asked of
 * WooCommerce at runtime, which is why a WooCommerce upgrade that adds a field
 * needs no edit here.
 */
final readonly class ResourceDescriptor {

	/**
	 * @param string                    $id          Stable slug, used in error messages and as a descriptor key.
	 * @param RestRoute                 $collection  Route addressing all of them, e.g. /wc/v3/coupons.
	 * @param RestRoute|null            $item        Route addressing one by id; null for singleton resources.
	 * @param string                    $singular    Noun as it reads mid-sentence, e.g. "coupon".
	 * @param string                    $plural      Plural of the same, and the key items come back under.
	 * @param list<OperationDescriptor> $operations
	 * @param RestRoute|null            $read_probe  Route to ask about read permission, when the collection cannot answer.
	 * @param RestRoute|null            $write_probe Same for writes.
	 * @param MetaOwner|null            $meta        Which metadata table this resource's custom fields live in; null offers none.
	 */
	public function __construct(
		public string $id,
		public ToolGroup $group,
		public RestRoute $collection,
		public ?RestRoute $item,
		public string $singular,
		public string $plural,
		public array $operations,
		public ?RestRoute $read_probe = null,
		public ?RestRoute $write_probe = null,
		public ?MetaOwner $meta = null,
	) {}

	/**
	 * The singular noun as an identifier, e.g. "product category" =>
	 * "product_category", so meta tool names read the same way the resource does.
	 */
	public function singular_slug(): string {
		return str_replace( ' ', '_', $this->singular );
	}

	/**
	 * @throws \LogicException When an item operation is declared on a resource with no item route.
	 */
	public function route_for( Operation $operation ): RestRoute {
		if ( ! $operation->targets_item() ) {
			return $this->collection;
		}

		if ( null === $this->item ) {
			throw new \LogicException(
				sprintf( 'Resource "%s" declares %s but has no item route.', $this->id, $operation->value )
			);
		}

		return $this->item;
	}

	/**
	 * The route whose permission callback decides whether these tools are shown.
	 *
	 * The collection by default, and for a reason: map_meta_cap() denies
	 * everyone an item capability asked without an id, so probing /coupons/{id}
	 * would hide the resource from administrators. Collection checks are
	 * id-independent even for nested resources — WooCommerce's own order-notes
	 * controller checks the post type without reading order_id — which is what
	 * makes this substitution sound. The overrides are for the handful of
	 * resources with no id-free collection at all.
	 */
	public function probe_route( ToolIntent $intent ): RestRoute {
		$override = ToolIntent::Write === $intent ? $this->write_probe : $this->read_probe;

		return $override ?? $this->collection;
	}
}
