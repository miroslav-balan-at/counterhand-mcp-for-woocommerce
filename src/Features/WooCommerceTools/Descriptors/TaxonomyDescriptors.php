<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * How the catalog is organised: categories, tags, attributes and the terms
 * under them, plus shipping classes.
 *
 * All five are WordPress taxonomies behind the same controller shape, which is
 * why one file covers them and why their collection arguments are identical —
 * that repetition is WooCommerce's, and naming it once here is the honest way
 * to reflect it.
 *
 * They are their own group rather than part of Products on purpose. A token
 * granted products:read today must not silently gain the ability to restructure
 * the catalog because a later release folded these in.
 */
final readonly class TaxonomyDescriptors implements DescriptorProvider {

	/** Every WooCommerce term collection takes exactly these. */
	private const TERM_QUERY = [ 'page', 'per_page', 'search', 'order', 'orderby', 'hide_empty', 'parent', 'product', 'slug', 'include', 'exclude' ];

	private const CATEGORY_FIELDS = [ 'id', 'name', 'slug', 'parent', 'description', 'display', 'image', 'menu_order', 'count' ];

	private const TAG_FIELDS = [ 'id', 'name', 'slug', 'description', 'count' ];

	private const ATTRIBUTE_FIELDS = [ 'id', 'name', 'slug', 'type', 'order_by', 'has_archives' ];

	private const TERM_FIELDS = [ 'id', 'name', 'slug', 'description', 'menu_order', 'count' ];

	private const SHIPPING_CLASS_FIELDS = [ 'id', 'name', 'slug', 'description', 'count' ];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			$this->categories(),
			$this->tags(),
			$this->attributes(),
			$this->attribute_terms(),
			$this->shipping_classes(),
		];
	}

	private function categories(): ResourceDescriptor {
		return new ResourceDescriptor(
			'product_categories',
			ToolGroup::Taxonomy,
			RestRoute::wc( '/products/categories' ),
			RestRoute::wc( '/products/categories/{id}' ),
			'product category',
			'product_categories',
			$this->crud(
				'product_categories',
				'product_category',
				self::CATEGORY_FIELDS,
				[ 'name', 'slug', 'parent', 'description', 'display', 'image' ],
				'Categories nest: pass parent to place one under another, and read parent back to see where it sits.',
				'Deleting a category does not delete its products — they simply lose the category. force is required, because WooCommerce does not trash terms.'
			)
		);
	}

	private function tags(): ResourceDescriptor {
		return new ResourceDescriptor(
			'product_tags',
			ToolGroup::Taxonomy,
			RestRoute::wc( '/products/tags' ),
			RestRoute::wc( '/products/tags/{id}' ),
			'product tag',
			'product_tags',
			$this->crud(
				'product_tags',
				'product_tag',
				self::TAG_FIELDS,
				[ 'name', 'slug', 'description' ],
				'Tags are flat — unlike categories they do not nest.',
				'force is required, because WooCommerce does not trash terms.'
			)
		);
	}

	/**
	 * Attributes are the definitions ("Colour"), not the values. The values are
	 * terms, which live one level down and need the attribute's id to address.
	 */
	private function attributes(): ResourceDescriptor {
		return new ResourceDescriptor(
			'product_attributes',
			ToolGroup::Taxonomy,
			RestRoute::wc( '/products/attributes' ),
			RestRoute::wc( '/products/attributes/{id}' ),
			'product attribute',
			'product_attributes',
			[
				new OperationDescriptor(
					ToolName::from( 'get_product_attributes' ),
					Operation::GetItems,
					new FieldProfile( [], self::ATTRIBUTE_FIELDS, false ),
					'These are the attribute definitions such as Colour or Size. Use get_attribute_terms with an attribute id to see the values it can take.'
				),
				new OperationDescriptor(
					ToolName::from( 'get_product_attribute' ),
					Operation::GetItem,
					new FieldProfile( [], self::ATTRIBUTE_FIELDS, false )
				),
				new OperationDescriptor(
					ToolName::from( 'create_product_attribute' ),
					Operation::CreateItem,
					new FieldProfile( [ 'name', 'slug', 'type', 'order_by', 'has_archives' ], self::ATTRIBUTE_FIELDS ),
					'Creating the attribute only creates the definition. Add its values with create_attribute_term.'
				),
				new OperationDescriptor(
					ToolName::from( 'update_product_attribute' ),
					Operation::UpdateItem,
					new FieldProfile( [ 'name', 'slug', 'type', 'order_by', 'has_archives' ], self::ATTRIBUTE_FIELDS )
				),
				new OperationDescriptor(
					ToolName::from( 'delete_product_attribute' ),
					Operation::DeleteItem,
					new FieldProfile( [ 'force' ], [ 'id', 'name' ], false ),
					'This deletes every term under the attribute as well, and detaches it from every product using it. force is required.'
				),
			]
		);
	}

	/**
	 * The one resource here whose own collection cannot answer the permission
	 * question.
	 *
	 * WooCommerce's terms controller resolves the taxonomy from attribute_id
	 * before checking anything, and the probe asks with placeholders unbound —
	 * so asking /products/attributes/{attribute_id}/terms yields "invalid
	 * taxonomy" and would hide these five tools from administrators too. The
	 * parent attributes collection is id-free and gated by the same manager
	 * capability, which makes it the honest thing to ask instead.
	 *
	 * Verified against WooCommerce 10.9.4: without this override all five are
	 * hidden; with it all five appear, and dispatch still runs WooCommerce's
	 * real check with the id in hand.
	 */
	private function attribute_terms(): ResourceDescriptor {
		$parent = RestRoute::wc( '/products/attributes' );

		return new ResourceDescriptor(
			'product_attribute_terms',
			ToolGroup::Taxonomy,
			RestRoute::wc( '/products/attributes/{attribute_id}/terms' ),
			RestRoute::wc( '/products/attributes/{attribute_id}/terms/{id}' ),
			'attribute term',
			'attribute_terms',
			$this->crud(
				'attribute_terms',
				'attribute_term',
				self::TERM_FIELDS,
				[ 'name', 'slug', 'description', 'menu_order' ],
				'The values one attribute can take, e.g. Red and Blue under Colour. attribute_id identifies which attribute.',
				'force is required, because WooCommerce does not trash terms.'
			),
			$parent,
			$parent
		);
	}

	private function shipping_classes(): ResourceDescriptor {
		return new ResourceDescriptor(
			'product_shipping_classes',
			ToolGroup::Taxonomy,
			RestRoute::wc( '/products/shipping_classes' ),
			RestRoute::wc( '/products/shipping_classes/{id}' ),
			'shipping class',
			'shipping_classes',
			$this->crud(
				'shipping_classes',
				'shipping_class',
				self::SHIPPING_CLASS_FIELDS,
				[ 'name', 'slug', 'description' ],
				'A shipping class groups products that ship alike; the rates themselves are set per shipping zone method.',
				'force is required, because WooCommerce does not trash terms.'
			)
		);
	}

	/**
	 * The five operations every WooCommerce term controller offers.
	 *
	 * Written once because WooCommerce implements them once: these controllers
	 * differ only in their writable field list and their nouns. Spelling out
	 * five near-identical OperationDescriptors per taxonomy would be four
	 * copies of the same decision, and copies drift.
	 *
	 * @param  list<string> $read_fields
	 * @param  list<string> $writable
	 * @return list<OperationDescriptor>
	 */
	private function crud(
		string $plural_name,
		string $singular_name,
		array $read_fields,
		array $writable,
		string $list_hint = '',
		string $delete_hint = ''
	): array {
		return [
			new OperationDescriptor(
				ToolName::from( 'get_' . $plural_name ),
				Operation::GetItems,
				new FieldProfile( self::TERM_QUERY, $read_fields, false ),
				$list_hint
			),
			new OperationDescriptor(
				ToolName::from( 'get_' . $singular_name ),
				Operation::GetItem,
				new FieldProfile( [], $read_fields, false )
			),
			new OperationDescriptor(
				ToolName::from( 'create_' . $singular_name ),
				Operation::CreateItem,
				new FieldProfile( $writable, $read_fields )
			),
			new OperationDescriptor(
				ToolName::from( 'update_' . $singular_name ),
				Operation::UpdateItem,
				new FieldProfile( $writable, $read_fields )
			),
			new OperationDescriptor(
				ToolName::from( 'delete_' . $singular_name ),
				Operation::DeleteItem,
				new FieldProfile( [ 'force' ], [ 'id', 'name' ], false ),
				$delete_hint
			),
		];
	}
}
