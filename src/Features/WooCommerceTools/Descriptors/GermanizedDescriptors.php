<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\LegalDetailsPolicy;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRequestGuards;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;

defined( 'ABSPATH' ) || exit;

/**
 * Germanized's lists, and the legal details it adds to wc/v3 products.
 *
 * Unlike Contact Form 7 its routes declare their arguments, so these are field names
 * only. The product tools stay closed: /products also accepts regular_price and
 * status, and a Germanized grant must not reach what products:write gates.
 *
 * Its bank details are deliberately absent. It decrypts them into every order and
 * customer response, which is why those profiles list their fields, and MetaKeyPolicy
 * refuses the keys.
 */
final readonly class GermanizedDescriptors implements DescriptorProvider {

	/**
	 * Any product write that omits min_age clears it, so products:write would lose an age
	 * check too. Every tool that writes through /products or its variations sends this.
	 */
	public const KEEP_AGE_CHECK = [ RestRequestGuards::KEEP_UNSENT => [ 'min_age' ] ];

	/** Germanized resets a deposit type's packaging on any write that omits it. */
	private const KEEP_PACKAGING = [ RestRequestGuards::KEEP_UNSENT => [ 'is_packaging', 'packaging', 'packaging_number_of_contents' ] ];

	/** A function Germanized defines. /products exists without it, so it cannot answer this. */
	private const PLUGIN_FUNCTION = 'WC_germanized';

	private const LIST_INPUT = [ 'page', 'per_page', 'search', 'include', 'hide_empty', 'order', 'orderby' ];

	private const TERM_FIELDS = [ 'name', 'slug', 'description' ];

	private const LEGAL_FIELDS = [
		'delivery_time',
		'country_specific_delivery_times',
		'sale_price_label',
		'sale_price_regular_label',
		'manufacturer',
		'unit',
		'unit_price',
		'mini_desc',
		'defect_description',
		'free_shipping',
		'safety_attachment_ids',
		'safety_instructions',
		'min_age',
		'warranty_attachment_id',
		'guarantee_length',
		'gtin',
		'mpn',
		'service',
		'used_good',
		'defective_copy',
		'is_food',
		'food',
		'wireless_electronic_device',
		'device_contains_power_supply',
		'device_charging_supports_usb_pd',
		'device_charging_watt_min',
		'device_charging_watt_max',
	];

	/**
	 * Readable only. differential_taxation sets the product's tax status to none, and
	 * clearing it does not bring the old status back. photovoltaic_system zero-rates it
	 * at checkout. Both reach what products:write gates.
	 */
	private const TAX_FIELDS = [ 'differential_taxation', 'photovoltaic_system' ];

	private const IDENTIFYING_FIELDS = [ 'id', 'name', 'sku', 'status', 'type' ];

	private const PRODUCT_OUTPUT = [ ...self::IDENTIFYING_FIELDS, ...self::LEGAL_FIELDS, ...self::TAX_FIELDS ];

	/** The view context gives a product with no delivery time the store's default. */
	private const AS_STORED = [ 'context' => 'edit' ];

	/**
	 * Germanized sanitizes these itself today. The marker is what still holds if a
	 * release starts trusting a user who has unfiltered_html, as CF7 and ACF do.
	 */
	private const WRITING = [ RestRequestGuards::NO_UNFILTERED_HTML => 1 ];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			$this->products(),
			$this->term_list( '/products/delivery_times', 'delivery time' ),
			$this->term_list( '/products/units', 'unit' ),
			$this->term_list( '/products/price_labels', 'price label' ),
			$this->term_list( '/products/deposit_types', 'deposit type', [ 'packaging_type', 'tax_status', 'is_packaging', 'packaging', 'packaging_number_of_contents', 'deposit' ], true ),
			$this->term_list( '/products/manufacturers', 'manufacturer', [ 'formatted_address', 'formatted_eu_address', 'garan_label_name' ] ),
			$this->term_list( '/nutrients', 'nutrient', [ 'order', 'unit', 'type', 'rounding_rule' ] ),
			$this->term_list( '/allergenic', 'allergen' ),
		];
	}

	private function group(): IntegrationGroup {
		return IntegrationGroup::of( 'germanized', copy: new GermanizedCopy() );
	}

	private function products(): ResourceDescriptor {
		return new ResourceDescriptor(
			'germanized_product_legal_details',
			$this->group(),
			RestRoute::wc( '/products' ),
			RestRoute::wc( '/products/{id}' ),
			'product',
			'products',
			[
				new OperationDescriptor(
					ToolName::from( 'get_products_legal_details' ),
					Operation::GetItems,
					new FieldProfile( [ 'page', 'per_page', 'search', 'include', 'category', 'status', 'sku', 'type' ], self::PRODUCT_OUTPUT, false ),
					description_override: 'List products with their Germanized legal details as stored: delivery time, unit price, deposit, manufacturer, food details. Germanized offers no filter on these, so to find products missing a delivery time, read every page (per_page up to 100, total_pages tells you when to stop) and look for an empty delivery_time. A product with none shows the store\'s default to customers, if one is set.',
					forced_params: self::AS_STORED
				),
				new OperationDescriptor(
					ToolName::from( 'get_product_legal_details' ),
					Operation::GetItem,
					new FieldProfile( [], self::PRODUCT_OUTPUT, false ),
					description_override: 'Read the Germanized legal details of one product as stored, by id. A product with no delivery time shows the store\'s default to customers, if one is set. Germanized always returns mini_desc, defect_description and safety_instructions as HTML paragraphs.',
					forced_params: self::AS_STORED
				),
				new OperationDescriptor(
					ToolName::from( 'update_product_legal_details' ),
					Operation::UpdateItem,
					new FieldProfile( [ 'id', ...self::LEGAL_FIELDS ], self::PRODUCT_OUTPUT, false ),
					description_override: 'Set Germanized legal details on one product. Only the fields you send change, and this cannot change its price, status or tax status. Terms such as delivery_time, unit and manufacturer take {"id": n} from their list tool, and a term that does not exist is refused; send null to clear one. country_specific_delivery_times merges by country, only an empty list clears them, and a country given the same delivery time as the product is not stored, because it would change nothing. Unit prices are worked out from unit_price when price_auto is true. A deposit type on a food product adds its amount to what customers pay.',
					forced_params: [ ...self::WRITING, ...self::KEEP_AGE_CHECK ],
					// These are statements the shop makes to customers and tax authorities.
					requires_confirmation: true,
					policy: new LegalDetailsPolicy()
				),
			],
			requires_function: self::PLUGIN_FUNCTION
		);
	}

	/**
	 * Delete needs force: these are terms, which have no trash, and removing one detaches
	 * it from every product using it.
	 *
	 * @param list<string> $extra_fields What this list adds to a term's name, slug and description.
	 * @param bool         $is_charged   Whether its values change what customers pay.
	 */
	private function term_list( string $route, string $singular, array $extra_fields = [], bool $is_charged = false ): ResourceDescriptor {
		$plural        = $singular . 's';
		$singular_name = str_replace( ' ', '_', $singular );
		$plural_name   = $singular_name . 's';
		$writable      = [ ...self::TERM_FIELDS, ...$extra_fields ];
		$output        = [ 'id', ...$writable, 'count' ];

		return new ResourceDescriptor(
			'germanized_' . $plural_name,
			$this->group(),
			RestRoute::wc( $route ),
			RestRoute::wc( $route . '/{id}' ),
			$singular,
			$plural,
			[
				new OperationDescriptor(
					ToolName::from( 'get_' . $plural_name ),
					Operation::GetItems,
					new FieldProfile( self::LIST_INPUT, $output, false ),
					description_override: sprintf( 'List Germanized %s. hide_empty leaves out those no product uses. Paginated: pass page and per_page.', $plural )
				),
				new OperationDescriptor(
					ToolName::from( 'create_' . $singular_name ),
					Operation::CreateItem,
					new FieldProfile( $writable, $output, false ),
					forced_params: self::WRITING
				),
				new OperationDescriptor(
					ToolName::from( 'update_' . $singular_name ),
					Operation::UpdateItem,
					new FieldProfile( $writable, $output, false ),
					$is_charged ? 'Only the fields you send change. This changes what customers are charged at checkout on every product using it.' : 'Every product using it shows the change.',
					forced_params: $is_charged ? [ ...self::WRITING, ...self::KEEP_PACKAGING ] : self::WRITING,
					requires_confirmation: $is_charged
				),
				new OperationDescriptor(
					ToolName::from( 'delete_' . $singular_name ),
					Operation::DeleteItem,
					new FieldProfile( [], [ 'id', 'name' ], false ),
					description_override: sprintf( 'Permanently delete a Germanized %s. There is no trash, and every product using it loses it.', $singular ),
					forced_params: [ 'force' => true ],
					requires_confirmation: true
				),
			]
		);
	}
}
