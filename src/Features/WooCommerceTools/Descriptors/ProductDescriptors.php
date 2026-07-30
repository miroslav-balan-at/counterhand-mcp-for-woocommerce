<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\MetaOwner;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * The catalogue: /wc/v3/products.
 *
 * A product is the largest schema in wc/v3 — roughly a hundred fields once
 * downloads, dimensions, attributes and meta are counted — which is why the
 * profiles below are the tightest in the plugin. They name the fields a store
 * owner actually talks about; describe_woocommerce_fields reaches the rest.
 */
final readonly class ProductDescriptors implements DescriptorProvider {

	/** Enough to pick a product out of a list and know whether it is sellable. */
	private const LIST_FIELDS = [
		'id',
		'name',
		'sku',
		'type',
		'status',
		'regular_price',
		'sale_price',
		'price',
		'stock_status',
		'stock_quantity',
		'categories',
		'permalink',
		'date_created',
	];

	/** One product, without the download, attribute and meta bulk. */
	private const ITEM_FIELDS = [
		'id',
		'name',
		'slug',
		'permalink',
		'type',
		'status',
		'featured',
		'description',
		'short_description',
		'sku',
		'price',
		'regular_price',
		'sale_price',
		'on_sale',
		'purchasable',
		'total_sales',
		'tax_status',
		'tax_class',
		'manage_stock',
		'stock_quantity',
		'stock_status',
		'backorders',
		'weight',
		'dimensions',
		'shipping_class',
		'average_rating',
		'rating_count',
		'categories',
		'tags',
		'images',
		'variations',
		'date_created',
		'date_modified',
	];

	/**
	 * What a product is authored with.
	 *
	 * type is here but absent from update: changing a live product's type
	 * orphans its variations, and an agent that wants a different type is
	 * better off creating a new product.
	 */
	private const CREATE_FIELDS = [
		'name',
		'type',
		'status',
		'regular_price',
		'sale_price',
		'description',
		'short_description',
		'sku',
		'manage_stock',
		'stock_quantity',
		'stock_status',
		'categories',
		'images',
	];

	private const UPDATE_FIELDS = [
		'name',
		'status',
		'regular_price',
		'sale_price',
		'description',
		'short_description',
		'sku',
		'manage_stock',
		'stock_quantity',
		'stock_status',
		'categories',
		'images',
	];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			new ResourceDescriptor(
				'products',
				ToolGroup::Products,
				RestRoute::wc( '/products' ),
				RestRoute::wc( '/products/{id}' ),
				'product',
				'products',
				$this->operations(),
				null,
				null,
				MetaOwner::Post
			),
		];
	}

	/** @return list<OperationDescriptor> */
	private function operations(): array {
		return [
			new OperationDescriptor(
				ToolName::from( 'list_products' ),
				Operation::GetItems,
				new FieldProfile(
					[ 'page', 'per_page', 'search', 'sku', 'status', 'type', 'category', 'stock_status', 'orderby', 'order' ],
					self::LIST_FIELDS,
					false
				),
				'Prices are strings in the shop currency. category takes a term id, which you can read off any product in the results.'
			),
			new OperationDescriptor(
				ToolName::from( 'get_product' ),
				Operation::GetItem,
				new FieldProfile( [], self::ITEM_FIELDS, false ),
				'Prices are strings in the shop currency. For a variable product, variations lists the variation ids.'
			),
			new OperationDescriptor(
				ToolName::from( 'create_product' ),
				Operation::CreateItem,
				new FieldProfile( self::CREATE_FIELDS, self::ITEM_FIELDS ),
				'SAFETY: the product is created as a draft unless you set status explicitly — tell the user to review and publish it, or call update_product with status "publish". Prices are strings in the shop currency, e.g. "9.99". images takes URLs to sideload; the first becomes the featured image.',
				null,
				[],
				// WooCommerce defaults a new product to publish. Nothing an
				// agent creates should go on sale without a human seeing it
				// first, and the agent can still say publish outright.
				[ 'status' => 'draft' ]
			),
			new OperationDescriptor(
				ToolName::from( 'update_product' ),
				Operation::UpdateItem,
				new FieldProfile( self::UPDATE_FIELDS, self::ITEM_FIELDS ),
				'Only the fields you pass are changed. Use status "publish" to take a draft live. categories replaces the stored list rather than adding to it.'
			),
			new OperationDescriptor(
				ToolName::from( 'delete_product' ),
				Operation::DeleteItem,
				new FieldProfile( [ 'force' ], [ 'id', 'name', 'sku', 'status' ], false ),
				'SAFETY: without force the product goes to the trash and can be restored. Pass force=true only when the user has asked for permanent deletion.'
			),
		];
	}
}
