<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\ReportTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class GetTopSellersTool extends AbstractWcTool {

	public function name(): string {
		return 'get_top_sellers';
	}

	public function description(): string {
		return 'Best-selling products (by quantity sold) for a period. Combine with get_product for details on each.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'period'   => [
					'type' => 'string',
					'enum' => [ 'week', 'month', 'last_month', 'year' ],
				],
				'date_min' => [
					'type'   => 'string',
					'format' => 'date',
				],
				'date_max' => [
					'type'   => 'string',
					'format' => 'date',
				],
			],
			'additionalProperties' => false,
		];
	}

	public function required_scope(): ApiScope {
		return ApiScope::ReportsRead;
	}

	public function group(): ToolGroup {
		return ToolGroup::Reports;
	}

	public function execute( array $arguments ): array {
		$items = $this->gateway->dispatch( 'GET', '/reports/top_sellers', $arguments );

		return [
			'top_sellers' => $this->shaper->shape_list( $items, [ 'name', 'product_id', 'quantity' ] ),
		];
	}
}
