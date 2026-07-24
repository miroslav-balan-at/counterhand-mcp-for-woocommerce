<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\ProductTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class DeleteProductTool extends AbstractWcTool {

	public function name(): string {
		return 'delete_product';
	}

	public function description(): string {
		return 'Delete a product. SAFETY: by default the product is moved to trash (recoverable). Pass force=true only when the user explicitly asks for permanent deletion.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'id'    => [
					'type'        => 'integer',
					'description' => 'Product id to delete.',
				],
				'force' => [
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'true = permanent delete, false = move to trash.',
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
		$deleted = $this->gateway->dispatch(
			'DELETE',
			'/products/' . (int) $arguments['id'],
			[ 'force' => (bool) ( $arguments['force'] ?? false ) ]
		);

		return [
			'id'     => $deleted['id'] ?? null,
			'name'   => $deleted['name'] ?? '',
			'status' => ( $arguments['force'] ?? false ) ? 'permanently_deleted' : 'trashed',
		];
	}
}
