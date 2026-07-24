<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\CustomerTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class ListCustomersTool extends AbstractWcTool {

	public function name(): string {
		return 'list_customers';
	}

	public function description(): string {
		return 'List registered customers. Guest buyers (no account) do not appear here — find them via list_orders instead. Paginated via page/per_page.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'search' => [
					'type'        => 'string',
					'description' => 'Free-text search over name, username and email.',
				],
				'email'  => [
					'type'        => 'string',
					'format'      => 'email',
					'description' => 'Exact email lookup.',
				],
				...$this->pagination_properties(),
			],
			'additionalProperties' => false,
		];
	}

	public function required_scope(): ApiScope {
		return ApiScope::CustomersRead;
	}

	public function group(): ToolGroup {
		return ToolGroup::Customers;
	}

	public function execute( array $arguments ): array {
		$items = $this->gateway->dispatch( 'GET', '/customers', $arguments );

		$customers = array_map(
			static fn ( array $customer ): array => [
				'id'           => $customer['id'] ?? null,
				'email'        => $customer['email'] ?? '',
				'first_name'   => $customer['first_name'] ?? '',
				'last_name'    => $customer['last_name'] ?? '',
				'date_created' => $customer['date_created'] ?? '',
				'company'      => $customer['billing']['company'] ?? '',
				'city'         => $customer['billing']['city'] ?? '',
				'country'      => $customer['billing']['country'] ?? '',
			],
			array_values( array_filter( $items, 'is_array' ) )
		);

		return [
			'customers' => $customers,
			'count'     => count( $customers ),
		];
	}
}
