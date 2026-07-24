<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\CustomerTools;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\AbstractWcTool;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

final readonly class GetCustomerTool extends AbstractWcTool {

	public function name(): string {
		return 'get_customer';
	}

	public function description(): string {
		return 'Get one registered customer by id, including billing and shipping addresses.';
	}

	public function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'id' => [
					'type'        => 'integer',
					'description' => 'Customer user id.',
				],
			],
			'required'             => [ 'id' ],
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
		$customer = $this->gateway->dispatch( 'GET', '/customers/' . (int) $arguments['id'] );

		return [
			'id'                 => $customer['id'] ?? null,
			'email'              => $customer['email'] ?? '',
			'first_name'         => $customer['first_name'] ?? '',
			'last_name'          => $customer['last_name'] ?? '',
			'username'           => $customer['username'] ?? '',
			'date_created'       => $customer['date_created'] ?? '',
			'is_paying_customer' => $customer['is_paying_customer'] ?? false,
			'billing'            => $customer['billing'] ?? [],
			'shipping'           => $customer['shipping'] ?? [],
		];
	}
}
