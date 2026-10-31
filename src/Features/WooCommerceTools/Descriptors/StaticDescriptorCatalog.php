<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;

defined( 'ABSPATH' ) || exit;

/**
 * Every resource this plugin knows how to expose, in the order it is offered.
 *
 * A provider per resource rather than one long array, so adding a resource is
 * adding a file and one line here — and so each file can carry the reasoning
 * for its own field choices next to them.
 */
final readonly class StaticDescriptorCatalog implements DescriptorProvider {

	/** @var list<DescriptorProvider> */
	private array $providers;

	public function __construct( DescriptorProvider ...$providers ) {
		$this->providers = [] === $providers ? self::shipped() : array_values( $providers );
	}

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		$resources = [];

		foreach ( $this->providers as $provider ) {
			$resources = [ ...$resources, ...$provider->resources() ];
		}

		/**
		 * Filters the resources exposed as MCP tools.
		 *
		 * The extension point for adding a custom post type or a third-party
		 * WooCommerce extension's endpoints without touching this plugin. Tools
		 * added here are gated exactly like the shipped ones — a resource still
		 * needs its group enabled, the token still needs the scope, and
		 * WooCommerce still runs its own permission check on every dispatch.
		 *
		 * @param list<ResourceDescriptor> $resources
		 */
		try {
			$filtered = apply_filters( 'counterhand_tool_descriptors', $resources );
		} catch ( \Throwable $throwable ) {
			// This runs at boot on every page, so another plugin's bad descriptor must not fatal the store.
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( 'counterhand_tool_descriptors failed: ' . $throwable->getMessage(), [ 'source' => 'counterhand-mcp' ] );
			}

			return $resources;
		}

		return array_values( array_filter( (array) $filtered, static fn ( mixed $r ): bool => $r instanceof ResourceDescriptor ) );
	}

	/** @return list<DescriptorProvider> */
	private static function shipped(): array {
		return [
			new ProductDescriptors(),
			new TaxonomyDescriptors(),
			new VariationDescriptors(),
			new ReviewDescriptors(),
			new OrderDescriptors(),
			new RefundDescriptors(),
			new CustomerDescriptors(),
			new ReportDescriptors(),
			new CouponDescriptors(),
			new ShippingDescriptors(),
			new TaxDescriptors(),
			new DataDescriptors(),
			new StoreConfigDescriptors(),
			new ContentDescriptors(),
			new SystemDescriptors(),
			new ContactForm7Descriptors(),
			new AcfDescriptors(),
			new GermanizedDescriptors(),
		];
	}
}
