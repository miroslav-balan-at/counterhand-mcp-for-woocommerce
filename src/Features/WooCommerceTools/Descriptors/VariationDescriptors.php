<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Descriptors;

use AgentGateMcp\Features\WooCommerceTools\Domain\DescriptorProvider;
use AgentGateMcp\Features\WooCommerceTools\Domain\FieldProfile;
use AgentGateMcp\Features\WooCommerceTools\Domain\Operation;
use AgentGateMcp\Features\WooCommerceTools\Domain\OperationDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ResourceDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ToolName;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestRoute;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * The variants of a variable product: /wc/v3/products/{product_id}/variations.
 *
 * A variation is where the price and the stock actually live for a variable
 * product — the parent carries a price *range*, not a price — so an agent
 * asked to change the cost of a size or colour has to come here.
 *
 * Nested under a product, which the route makes unavoidable: every operation
 * needs product_id, and get_product's variations field is where the ids come
 * from.
 */
final readonly class VariationDescriptors implements DescriptorProvider {

	private const LIST_FIELDS = [
		'id',
		'sku',
		'status',
		'price',
		'regular_price',
		'sale_price',
		'on_sale',
		'stock_status',
		'stock_quantity',
		'attributes',
		'menu_order',
	];

	private const ITEM_FIELDS = [
		'id',
		'description',
		'permalink',
		'sku',
		'status',
		'price',
		'regular_price',
		'sale_price',
		'on_sale',
		'purchasable',
		'virtual',
		'downloadable',
		'tax_status',
		'tax_class',
		'manage_stock',
		'stock_quantity',
		'stock_status',
		'backorders',
		'weight',
		'dimensions',
		'shipping_class',
		'image',
		'attributes',
		'menu_order',
		'date_created',
		'date_modified',
	];

	private const WRITABLE_FIELDS = [
		'description',
		'sku',
		'status',
		'regular_price',
		'sale_price',
		'virtual',
		'downloadable',
		'tax_class',
		'manage_stock',
		'stock_quantity',
		'stock_status',
		'backorders',
		'weight',
		'dimensions',
		'shipping_class',
		'image',
		'attributes',
		'menu_order',
	];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			new ResourceDescriptor(
				'product_variations',
				ToolGroup::Variations,
				RestRoute::wc( '/products/{product_id}/variations' ),
				RestRoute::wc( '/products/{product_id}/variations/{id}' ),
				'product variation',
				'variations',
				$this->operations()
			),
		];
	}

	/** @return list<OperationDescriptor> */
	private function operations(): array {
		return [
			new OperationDescriptor(
				ToolName::from( 'get_product_variations' ),
				Operation::GetItems,
				new FieldProfile(
					[ 'page', 'per_page', 'search', 'status', 'sku', 'stock_status', 'on_sale', 'order', 'orderby', 'include', 'exclude' ],
					self::LIST_FIELDS,
					false
				),
				'product_id is the variable product these belong to. attributes says which combination each variation represents, e.g. Colour: Red, Size: L.'
			),
			new OperationDescriptor(
				ToolName::from( 'get_product_variation' ),
				Operation::GetItem,
				new FieldProfile( [], self::ITEM_FIELDS, false )
			),
			new OperationDescriptor(
				ToolName::from( 'create_product_variation' ),
				Operation::CreateItem,
				new FieldProfile( self::WRITABLE_FIELDS, self::ITEM_FIELDS ),
				'attributes must name a combination the parent product actually offers, and each combination can exist only once.'
			),
			new OperationDescriptor(
				ToolName::from( 'update_product_variation' ),
				Operation::UpdateItem,
				new FieldProfile( self::WRITABLE_FIELDS, self::ITEM_FIELDS ),
				'This is where a variable product\'s price and stock are changed — setting them on the parent has no effect.'
			),
			new OperationDescriptor(
				ToolName::from( 'delete_product_variation' ),
				Operation::DeleteItem,
				new FieldProfile( [ 'force' ], [ 'id', 'sku' ], false ),
				'SAFETY: without force the variation goes to the trash and can be restored.'
			),
		];
	}
}
