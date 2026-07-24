<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\ProductTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class GetProductTool extends AbstractWcTool {

	private const DETAIL_FIELDS = [
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
		'manage_stock',
		'categories',
		'tags',
		'images',
		'permalink',
		'date_created',
		'date_modified',
		'weight',
		'dimensions',
		'attributes',
	];

	public function name(): string {
		return 'get_product';
	}

	public function description(): string {
		return 'Get one product by id, including description, images, categories and stock. Prices are strings in the shop currency.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'id' => [
					'type'        => 'integer',
					'description' => 'Product id.',
				],
			],
			'required'             => [ 'id' ],
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
		$item = $this->gateway->dispatch( 'GET', '/products/' . (int) $arguments['id'] );

		$shaped = $this->shaper->shape_item( $item, self::DETAIL_FIELDS );

		$shaped['description']       = $this->shaper->strip_html( $item['description'] ?? '' );
		$shaped['short_description'] = $this->shaper->strip_html( $item['short_description'] ?? '' );
		$shaped['images']            = array_map(
			static fn ( array $image ): array => [ 'id' => $image['id'] ?? null, 'src' => $image['src'] ?? '' ],
			is_array( $shaped['images'] ?? null ) ? $shaped['images'] : []
		);

		return $shaped;
	}
}
