<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Descriptors;

use AgentGateMcp\Features\WooCommerceTools\Domain\DescriptorProvider;
use AgentGateMcp\Features\WooCommerceTools\Domain\FieldProfile;
use AgentGateMcp\Features\WooCommerceTools\Domain\MetaOwner;
use AgentGateMcp\Features\WooCommerceTools\Domain\Operation;
use AgentGateMcp\Features\WooCommerceTools\Domain\OperationDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ResourceDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ToolName;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestRoute;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Account holders: /wc/v3/customers.
 *
 * The only group whose every field is personal data, which is why it ships
 * disabled and why these profiles publish no meta_data on either side —
 * customer meta is WordPress usermeta, where a store's other plugins keep
 * things a shop assistant has no business reading.
 */
final readonly class CustomerDescriptors implements DescriptorProvider {

	private const LIST_FIELDS = [
		'id',
		'email',
		'first_name',
		'last_name',
		'username',
		'date_created',
		'orders_count',
		'total_spent',
	];

	private const ITEM_FIELDS = [
		'id',
		'email',
		'first_name',
		'last_name',
		'username',
		'role',
		'date_created',
		'date_modified',
		'orders_count',
		'total_spent',
		'avatar_url',
		'billing',
		'shipping',
	];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			new ResourceDescriptor(
				'customers',
				ToolGroup::Customers,
				RestRoute::wc( '/customers' ),
				RestRoute::wc( '/customers/{id}' ),
				'customer',
				'customers',
				$this->operations(),
				null,
				null,
				MetaOwner::User
			),
		];
	}

	/** @return list<OperationDescriptor> */
	private function operations(): array {
		return [
			new OperationDescriptor(
				ToolName::from( 'list_customers' ),
				Operation::GetItems,
				new FieldProfile(
					[ 'page', 'per_page', 'search', 'email', 'role', 'order', 'orderby' ],
					self::LIST_FIELDS,
					false
				),
				'Registered account holders only. Someone who checked out as a guest has no customer record — find them through list_orders and the billing email instead.'
			),
			new OperationDescriptor(
				ToolName::from( 'get_customer' ),
				Operation::GetItem,
				new FieldProfile( [], self::ITEM_FIELDS, false ),
				'Includes the billing and shipping addresses on file. This is personal data: repeat it back only as far as the user\'s question needs.'
			),
		];
	}
}
