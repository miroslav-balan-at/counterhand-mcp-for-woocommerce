<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\ProductTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class UpdateProductTool extends AbstractWcTool {

	public function name(): string {
		return 'update_product';
	}

	public function description(): string {
		return 'Update fields of an existing product by id. Only the provided fields change. Use status "publish" to make a draft product live.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'id'                => [
					'type'        => 'integer',
					'description' => 'Product id to update.',
				],
				'name'              => [ 'type' => 'string' ],
				'status'            => [
					'type' => 'string',
					'enum' => [ 'draft', 'pending', 'private', 'publish' ],
				],
				'regular_price'     => [ 'type' => 'string' ],
				'sale_price'        => [
					'type'        => 'string',
					'description' => 'Set to empty string "" to remove the sale price.',
				],
				'description'       => [ 'type' => 'string' ],
				'short_description' => [ 'type' => 'string' ],
				'sku'               => [ 'type' => 'string' ],
				'manage_stock'      => [ 'type' => 'boolean' ],
				'stock_quantity'    => [ 'type' => 'integer' ],
				'stock_status'      => [
					'type' => 'string',
					'enum' => [ 'instock', 'outofstock', 'onbackorder' ],
				],
				'categories'        => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
				],
			],
			'required'             => [ 'id' ],
			'additionalProperties' => false,
		];
	}

	public function required_scope(): ApiScope {
		return ApiScope::ProductsWrite;
	}

	public function group(): ToolGroup {
		return ToolGroup::Products;
	}

	public function execute( array $arguments ): array {
		$product_id = (int) $arguments['id'];
		$payload    = $arguments;
		unset( $payload['id'] );

		if ( isset( $payload['categories'] ) ) {
			$payload['categories'] = array_map(
				static fn ( int $term_id ): array => [ 'id' => $term_id ],
				$payload['categories']
			);
		}

		$updated = $this->gateway->dispatch( 'PUT', '/products/' . $product_id, $payload );

		return [
			'id'        => $updated['id'] ?? null,
			'name'      => $updated['name'] ?? '',
			'status'    => $updated['status'] ?? '',
			'price'     => $updated['price'] ?? '',
			'permalink' => $updated['permalink'] ?? '',
		];
	}
}
