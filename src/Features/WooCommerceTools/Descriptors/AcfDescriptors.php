<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\WooCommerceTools\Domain\AcfFieldNamePolicy;
use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRequestGuards;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;

defined( 'ABSPATH' ) || exit;

/**
 * ACF field values, through the `acf` field it adds to wp/v2 routes.
 *
 * wc/v3 does not carry that field, so products go through wp/v2/product.
 *
 * Only `id` is published from the route, and these tools take nothing else. The
 * routes also accept title, status and the rest, and an integration grant must not
 * reach what the products and content scopes gate. `acf` is declared because ACF
 * adds it to the route table only for some requests.
 */
final readonly class AcfDescriptors implements DescriptorProvider {

	/** A function ACF defines. The routes exist without it, so they cannot answer this. */
	private const PLUGIN_FUNCTION = 'acf_get_field_groups';

	private const OUTPUT_FIELDS = [ 'id', 'title', 'acf' ];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			$this->resource( 'acf_posts', 'posts', 'post' ),
			$this->resource( 'acf_pages', 'pages', 'page' ),
			$this->resource( 'acf_products', 'product', 'product' ),
		];
	}

	private function resource( string $id, string $route, string $noun ): ResourceDescriptor {
		return new ResourceDescriptor(
			$id,
			IntegrationGroup::of( 'acf', copy: new AcfCopy() ),
			RestRoute::wp( '/' . $route ),
			RestRoute::wp( '/' . $route . '/{id}' ),
			$noun,
			$noun . 's',
			[
				new OperationDescriptor(
					ToolName::from( 'get_' . $noun . '_custom_fields' ),
					Operation::GetItem,
					new FieldProfile( [ 'id' ], self::OUTPUT_FIELDS, false ),
					description_override: sprintf( 'Read the Advanced Custom Fields values on one %s, by id. acf holds them by field name. A field group without "Show in REST API" switched on is not included, and password fields are never returned.', $noun ),
					forced_params: [ RestRequestGuards::WITHHOLD_PASSWORDS => 1 ]
				),
				new OperationDescriptor(
					ToolName::from( 'update_' . $noun . '_custom_fields' ),
					Operation::UpdateItem,
					new FieldProfile( [ 'id' ], self::OUTPUT_FIELDS, false ),
					description_override: sprintf( 'Change Advanced Custom Fields values on one %1$s. Only the fields you name in acf change, and null clears one. Read the %1$s first to see the field names. Image, file and gallery fields take attachment ids. This cannot change anything else about it, and password fields cannot be set here.', $noun ),
					forced_params: [
						RestRequestGuards::WITHHOLD_PASSWORDS => 1,
						RestRequestGuards::NO_UNFILTERED_HTML => 1,
					],
					policy: new AcfFieldNamePolicy(),
					declared_arguments: [
						'acf' => [
							'type'                 => 'object',
							'description'          => 'Field values by field name.',
							'additionalProperties' => true,
						],
					]
				),
			],
			requires_function: self::PLUGIN_FUNCTION
		);
	}
}
