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
 * WooCommerce's own reference lists: /wc/v3/data.
 *
 * The safest group in the plugin — static tables of countries, regions and
 * currencies, identical on every store, with no write axis at all. It exists
 * because the codes are load-bearing everywhere else: a tax rate wants a
 * country code, a shipping zone wants a state code, and an agent that guesses
 * "UK" where WooCommerce means "GB" writes a rule that silently matches nobody.
 */
final readonly class DataDescriptors implements DescriptorProvider {

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			$this->countries(),
			$this->continents(),
			$this->currencies(),
			$this->current_currency(),
		];
	}

	private function countries(): ResourceDescriptor {
		return new ResourceDescriptor(
			'data_countries',
			ToolGroup::Data,
			RestRoute::wc( '/data/countries' ),
			RestRoute::wc( '/data/countries/{location}' ),
			'country',
			'countries',
			[
				new OperationDescriptor(
					ToolName::from( 'get_countries' ),
					Operation::GetItems,
					new FieldProfile( [], [ 'code', 'name' ], false ),
					'',
					'Every country WooCommerce knows, as a two-letter code and a name. These codes are what tax rates, shipping zones and customer addresses use, so look one up here rather than guessing it.'
				),
				new OperationDescriptor(
					ToolName::from( 'get_country' ),
					Operation::GetItem,
					new FieldProfile( [], [ 'code', 'name', 'states' ], false ),
					'location is the two-letter country code. states lists the regions WooCommerce recognises inside it, which is where a shipping zone\'s state codes come from.'
				),
			]
		);
	}

	private function continents(): ResourceDescriptor {
		return new ResourceDescriptor(
			'data_continents',
			ToolGroup::Data,
			RestRoute::wc( '/data/continents' ),
			RestRoute::wc( '/data/continents/{location}' ),
			'continent',
			'continents',
			[
				new OperationDescriptor(
					ToolName::from( 'get_continents' ),
					Operation::GetItems,
					new FieldProfile( [], [ 'code', 'name', 'countries' ], false ),
					'',
					'Continents and the countries in each. A shipping zone can cover a whole continent by code, which is shorter than listing its countries.'
				),
				new OperationDescriptor(
					ToolName::from( 'get_continent' ),
					Operation::GetItem,
					new FieldProfile( [], [ 'code', 'name', 'countries' ], false )
				),
			]
		);
	}

	private function currencies(): ResourceDescriptor {
		return new ResourceDescriptor(
			'data_currencies',
			ToolGroup::Data,
			RestRoute::wc( '/data/currencies' ),
			RestRoute::wc( '/data/currencies/{currency}' ),
			'currency',
			'currencies',
			[
				new OperationDescriptor(
					ToolName::from( 'get_currencies' ),
					Operation::GetItems,
					new FieldProfile( [], [ 'code', 'name', 'symbol' ], false ),
					'',
					'Every currency WooCommerce knows, with its ISO code and symbol. This is the reference list, not what the store trades in — get_current_currency answers that.'
				),
				new OperationDescriptor(
					ToolName::from( 'get_currency' ),
					Operation::GetItem,
					new FieldProfile( [], [ 'code', 'name', 'symbol' ], false ),
					'currency is the three-letter ISO code, e.g. EUR.'
				),
			]
		);
	}

	/**
	 * A singleton: one currency, addressed by a fixed path with no id.
	 *
	 * Declared as GetItem against a route equal to its own collection, which is
	 * how this model expresses "there is exactly one of these". GetItems would
	 * be wrong in a way that is easy to miss — WooCommerce answers with a flat
	 * object, and shaping that as a collection yields an empty list and a count
	 * of zero rather than the currency.
	 */
	private function current_currency(): ResourceDescriptor {
		$route = RestRoute::wc( '/data/currencies/current' );

		return new ResourceDescriptor(
			'data_current_currency',
			ToolGroup::Data,
			$route,
			$route,
			'current_currency',
			'current_currencies',
			[
				new OperationDescriptor(
					ToolName::from( 'get_current_currency' ),
					Operation::GetItem,
					new FieldProfile( [], [ 'code', 'name', 'symbol' ], false ),
					'',
					'The currency this store actually trades in. Every price and total returned by the other tools is in this currency, so read it once before quoting an amount back to anyone. Takes no arguments.'
				),
			]
		);
	}
}
