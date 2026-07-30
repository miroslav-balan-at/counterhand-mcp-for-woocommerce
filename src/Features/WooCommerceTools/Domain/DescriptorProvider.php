<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * A source of resource descriptors.
 *
 * One interface rather than a separate catalog contract, because a catalog is
 * just a provider that happens to be made of other providers — the composite
 * and its parts answer the same question, so anything consuming descriptors can
 * be handed either without knowing which it got.
 */
interface DescriptorProvider {

	/** @return list<ResourceDescriptor> */
	public function resources(): array;
}
