<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\ProductTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class CreateProductTool extends AbstractWcTool {

	public function name(): string {
		return 'create_product';
	}

	public function description(): string {
		return 'Create a new product. SAFETY: the product is created with status "draft" unless status is set explicitly — tell the user to review and publish it in the store admin, or call update_product with status "publish". Prices are strings in the shop currency (e.g. "9.99").';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'name'              => [
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'Product title.',
				],
				'type'              => [
					'type'    => 'string',
					'enum'    => [ 'simple', 'grouped', 'external', 'variable' ],
					'default' => 'simple',
				],
				'status'            => [
					'type'        => 'string',
					'enum'        => [ 'draft', 'pending', 'private', 'publish' ],
					'default'     => 'draft',
					'description' => 'Defaults to draft so a human can review before it goes live.',
				],
				'regular_price'     => [
					'type'        => 'string',
					'description' => 'Regular price as a string, e.g. "19.90".',
				],
				'sale_price'        => [
					'type'        => 'string',
					'description' => 'Optional discounted price as a string.',
				],
				'description'       => [
					'type'        => 'string',
					'description' => 'Long description. Plain text or simple HTML.',
				],
				'short_description' => [
					'type'        => 'string',
					'description' => 'Short summary shown near the price.',
				],
				'sku'               => [
					'type'        => 'string',
					'description' => 'Unique SKU. Creation fails if it already exists.',
				],
				'manage_stock'      => [
					'type'    => 'boolean',
					'default' => false,
				],
				'stock_quantity'    => [
					'type'        => 'integer',
					'description' => 'Only used when manage_stock is true.',
				],
				'stock_status'      => [
					'type' => 'string',
					'enum' => [ 'instock', 'outofstock', 'onbackorder' ],
				],
				'categories'        => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'Category term ids. Discover ids via list_products results.',
				],
				'images'            => [
					'type'        => 'array',
					'items'       => [
						'type'   => 'string',
						'format' => 'uri',
					],
					'description' => 'Image URLs to sideload; the first becomes the featured image.',
				],
			],
			'required'             => [ 'name' ],
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
		$payload = $arguments;

		if ( isset( $payload['categories'] ) ) {
			$payload['categories'] = array_map(
				static fn ( int $term_id ): array => [ 'id' => $term_id ],
				$payload['categories']
			);
		}

		if ( isset( $payload['images'] ) ) {
			$payload['images'] = array_map(
				static fn ( string $url ): array => [ 'src' => $url ],
				$payload['images']
			);
		}

		$created = $this->gateway->dispatch( 'POST', '/products', $payload );

		return [
			'id'        => $created['id'] ?? null,
			'name'      => $created['name'] ?? '',
			'status'    => $created['status'] ?? '',
			'sku'       => $created['sku'] ?? '',
			'price'     => $created['price'] ?? '',
			'permalink' => $created['permalink'] ?? '',
			'edit_url'  => admin_url( 'post.php?post=' . (int) ( $created['id'] ?? 0 ) . '&action=edit' ),
		];
	}
}
