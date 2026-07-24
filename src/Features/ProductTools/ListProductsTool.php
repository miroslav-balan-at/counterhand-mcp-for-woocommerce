<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\ProductTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class ListProductsTool extends AbstractWcTool {

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

	public function name(): string {
		return 'list_products';
	}

	public function description(): string {
		return 'List products in the store. Prices are strings in the shop currency. Results are paginated: pass page/per_page (max 50) and repeat with the next page until fewer than per_page items return.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'search'       => [
					'type'        => 'string',
					'description' => 'Free-text search over product name and content.',
				],
				'sku'          => [
					'type'        => 'string',
					'description' => 'Exact SKU to look up.',
				],
				'status'       => [
					'type'    => 'string',
					'enum'    => [ 'any', 'draft', 'pending', 'private', 'publish' ],
					'default' => 'any',
				],
				'type'         => [
					'type' => 'string',
					'enum' => [ 'simple', 'grouped', 'external', 'variable' ],
				],
				'category'     => [
					'type'        => 'integer',
					'description' => 'Category term id to filter by.',
				],
				'stock_status' => [
					'type' => 'string',
					'enum' => [ 'instock', 'outofstock', 'onbackorder' ],
				],
				'orderby'      => [
					'type' => 'string',
					'enum' => [ 'date', 'id', 'title', 'price', 'popularity', 'rating' ],
				],
				'order'        => [
					'type' => 'string',
					'enum' => [ 'asc', 'desc' ],
				],
				...$this->pagination_properties(),
			],
			'additionalProperties' => false,
		];
	}

	public function required_scope(): ApiScope {
		return ApiScope::ProductsRead;
	}

	public function group(): ToolGroup {
		return ToolGroup::Products;
	}

	public function execute( array $arguments ): array {
		$items = $this->gateway->dispatch( 'GET', '/products', $arguments );

		return [
			'products' => $this->shaper->shape_list( $items, self::LIST_FIELDS ),
			'count'    => count( $items ),
		];
	}
}
