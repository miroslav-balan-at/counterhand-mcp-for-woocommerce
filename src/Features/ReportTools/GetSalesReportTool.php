<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\ReportTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class GetSalesReportTool extends AbstractWcTool {

	public function name(): string {
		return 'get_sales_report';
	}

	public function description(): string {
		return 'Sales totals (revenue, order count, items sold, refunds, discounts) for a period. Use period for common ranges or date_min/date_max (YYYY-MM-DD) for a custom range. Amounts are strings in the shop currency.';
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
					'type'        => 'string',
					'format'      => 'date',
					'description' => 'Custom range start, YYYY-MM-DD. Overrides period.',
				],
				'date_max' => [
					'type'        => 'string',
					'format'      => 'date',
					'description' => 'Custom range end, YYYY-MM-DD.',
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
		$report = $this->gateway->dispatch( 'GET', '/reports/sales', $arguments );

		// wc/v3 wraps the single report object in an array.
		$sales = is_array( $report[0] ?? null ) ? $report[0] : $report;

		return $this->shaper->shape_item(
			$sales,
			[
				'total_sales',
				'net_sales',
				'total_orders',
				'total_items',
				'total_tax',
				'total_shipping',
				'total_refunds',
				'total_discount',
				'totals_grouped_by',
				'totals',
			]
		);
	}
}
