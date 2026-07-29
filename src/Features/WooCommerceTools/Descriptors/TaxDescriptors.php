<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Descriptors;

use AgentGateMcp\Features\WooCommerceTools\Domain\DescriptorProvider;
use AgentGateMcp\Features\WooCommerceTools\Domain\FieldProfile;
use AgentGateMcp\Features\WooCommerceTools\Domain\Operation;
use AgentGateMcp\Features\WooCommerceTools\Domain\OperationDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ResourceDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ToolName;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestRoute;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Tax rates and the classes products are assigned to.
 *
 * Writes here change what customers are charged and what the store owes, which
 * is a legal matter rather than a merchandising one. The tools say so, and the
 * group ships disabled.
 */
final readonly class TaxDescriptors implements DescriptorProvider {

	private const RATE_FIELDS = [
		'id',
		'country',
		'state',
		'postcode',
		'city',
		'rate',
		'name',
		'priority',
		'compound',
		'shipping',
		'order',
		'class',
	];

	private const WRITABLE_FIELDS = [
		'country',
		'state',
		'postcode',
		'city',
		'rate',
		'name',
		'priority',
		'compound',
		'shipping',
		'order',
		'class',
		'postcodes',
		'cities',
	];

	private const CLASS_FIELDS = [ 'slug', 'name' ];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			$this->rates(),
			$this->classes(),
		];
	}

	private function rates(): ResourceDescriptor {
		return new ResourceDescriptor(
			'tax_rates',
			ToolGroup::Taxes,
			RestRoute::wc( '/taxes' ),
			RestRoute::wc( '/taxes/{id}' ),
			'tax rate',
			'tax_rates',
			[
				new OperationDescriptor(
					ToolName::from( 'get_tax_rates' ),
					Operation::GetItems,
					new FieldProfile( [ 'page', 'per_page', 'offset', 'order', 'orderby', 'class' ], self::RATE_FIELDS, false ),
					'rate is a percentage as a decimal string, e.g. "20.0000". class filters to one tax class; omit it for the standard rates.'
				),
				new OperationDescriptor(
					ToolName::from( 'get_tax_rate' ),
					Operation::GetItem,
					new FieldProfile( [], self::RATE_FIELDS, false )
				),
				new OperationDescriptor(
					ToolName::from( 'create_tax_rate' ),
					Operation::CreateItem,
					new FieldProfile( self::WRITABLE_FIELDS, self::RATE_FIELDS ),
					'SAFETY: this changes what customers are charged at checkout from the moment it is saved. An empty country matches everywhere, so leaving it blank applies the rate worldwide. Confirm the country, the rate and the class with the user first.'
				),
				new OperationDescriptor(
					ToolName::from( 'update_tax_rate' ),
					Operation::UpdateItem,
					new FieldProfile( self::WRITABLE_FIELDS, self::RATE_FIELDS ),
					'SAFETY: takes effect on the next checkout. It does not alter tax already charged on existing orders.'
				),
				new OperationDescriptor(
					ToolName::from( 'delete_tax_rate' ),
					Operation::DeleteItem,
					new FieldProfile( [ 'force' ], [ 'id', 'country', 'rate', 'name' ], false ),
					'SAFETY: customers matching this rate stop being charged it immediately. force is required — WooCommerce does not trash tax rates.'
				),
			]
		);
	}

	/**
	 * Classes have no update route in WooCommerce: a class is a slug and a name,
	 * and renaming means deleting and recreating.
	 */
	private function classes(): ResourceDescriptor {
		return new ResourceDescriptor(
			'tax_classes',
			ToolGroup::Taxes,
			RestRoute::wc( '/taxes/classes' ),
			RestRoute::wc( '/taxes/classes/{slug}' ),
			'tax class',
			'tax_classes',
			[
				new OperationDescriptor(
					ToolName::from( 'get_tax_classes' ),
					Operation::GetItems,
					new FieldProfile( [], self::CLASS_FIELDS, false ),
					'',
					'The tax classes this store defines — Standard plus any it has added, such as reduced or zero rate. A product\'s tax_class field names one of these slugs, and each class has its own set of rates.'
				),
				new OperationDescriptor(
					ToolName::from( 'create_tax_class' ),
					Operation::CreateItem,
					new FieldProfile( [ 'name' ], self::CLASS_FIELDS ),
					'A new class starts with no rates in it, so nothing is charged under it until you add some with create_tax_rate.'
				),
				new OperationDescriptor(
					ToolName::from( 'delete_tax_class' ),
					Operation::DeleteItem,
					new FieldProfile( [ 'force' ], self::CLASS_FIELDS, false ),
					'SAFETY: this deletes every rate in the class, and products assigned to it fall back to the standard rate. force is required.'
				),
			]
		);
	}
}
