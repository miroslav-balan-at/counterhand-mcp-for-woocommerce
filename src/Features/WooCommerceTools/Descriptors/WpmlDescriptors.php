<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\ExistingTranslationPolicy;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\NewTranslationPolicy;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Domain\TranslatedElements;
use Counterhand\Features\WooCommerceTools\Domain\TranslationListPolicy;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRequestGuards;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;

defined( 'ABSPATH' ) || exit;

/**
 * Translations of products and their terms, through what WooCommerce Multilingual adds
 * to wc/v3. It declares none of it, so lang and translation_of are declared here.
 *
 * WPML alone adds nothing to these routes, and Multilingual without WPML ignores lang,
 * so both must be active. The tools stay closed: these routes also accept prices and
 * status, and a WPML grant must not reach what products:write gates. Multilingual copies
 * prices, SKU and stock from the original, so a translation has no need to send them, and
 * it creates a translated product's variations by itself, so they are only ever edited.
 */
final readonly class WpmlDescriptors implements DescriptorProvider {

	private const PLUGIN_FUNCTIONS = [ 'wpml_element_language_details_filter', 'wcml_is_multi_currency_on' ];

	private const LIST_INPUT = [ 'page', 'per_page', 'search', 'include', 'orderby', 'order' ];

	private const LANGUAGE = [
		'lang' => [
			'type'        => 'string',
			'description' => 'Language code, such as en. Without it, only the store\'s default language is listed. Ask for one language at a time.',
		],
	];

	private const NEW_TRANSLATION = [
		'lang'           => [
			'type'        => 'string',
			'description' => 'Language code of the translation, such as en.',
			'required'    => true,
		],
		'translation_of' => [
			'type'        => 'integer',
			'description' => 'Id of the original.',
			'required'    => true,
		],
	];

	private const PRODUCT_TEXT = [ 'name', 'slug', 'description', 'short_description' ];

	private const PRODUCT_LIST_OUTPUT = [ 'id', 'name', 'sku', 'status', 'type', 'lang', 'translations' ];

	private const PRODUCT_OUTPUT = [ 'id', ...self::PRODUCT_TEXT, 'status', 'lang', 'translations' ];

	private const PRODUCT_WRITE = [
		RestRequestGuards::NO_UNFILTERED_HTML => 1,
		...GermanizedDescriptors::KEEP_AGE_CHECK,
	];

	private const TERM_TEXT = [ 'name', 'slug', 'description' ];

	private const TERM_OUTPUT = [ 'id', ...self::TERM_TEXT, 'lang', 'translations' ];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			$this->products(),
			$this->variations(),
			$this->terms( '/products/categories', TranslatedElements::terms( 'product_cat' ), 'wpml_product_categories', 'product category', 'product categories', 'product_category' ),
			$this->terms( '/products/tags', TranslatedElements::terms( 'product_tag' ), 'wpml_product_tags', 'product tag', 'product tags', 'product_tag' ),
			$this->terms( '/products/attributes/{attribute_id}/terms', TranslatedElements::attribute_terms(), 'wpml_attribute_terms', 'attribute term', 'attribute terms', 'attribute_term', RestRoute::wc( '/products/attributes' ) ),
		];
	}

	public static function group(): IntegrationGroup {
		return IntegrationGroup::of( 'wpml', copy: new WpmlCopy() );
	}

	private function products(): ResourceDescriptor {
		$elements = TranslatedElements::posts( 'product' );
		$existing = new ExistingTranslationPolicy( $elements );

		return new ResourceDescriptor(
			'wpml_products',
			self::group(),
			RestRoute::wc( '/products' ),
			RestRoute::wc( '/products/{id}' ),
			'product',
			'products',
			[
				new OperationDescriptor(
					ToolName::from( 'get_product_translations' ),
					Operation::GetItems,
					new FieldProfile( [ ...self::LIST_INPUT, 'status' ], self::PRODUCT_LIST_OUTPUT, false ),
					description_override: 'List products with their language and the id of each translation. To find what is missing in a language, read every page in the default language (total_pages tells you when to stop) and look for that language missing from translations; on a store showing untranslated products in other languages, translations is the only reliable sign. include with no lang answers with the default language\'s id, so send lang to read a translation by id.',
					policy: new TranslationListPolicy(),
					declared_arguments: self::LANGUAGE
				),
				new OperationDescriptor(
					ToolName::from( 'get_product_translation' ),
					Operation::GetItem,
					new FieldProfile( [ 'id' ], self::PRODUCT_OUTPUT, false ),
					description_override: 'Read the text of one product in its own language, by id, to translate it or check a translation.'
				),
				new OperationDescriptor(
					ToolName::from( 'create_product_translation' ),
					Operation::CreateItem,
					new FieldProfile( self::PRODUCT_TEXT, self::PRODUCT_OUTPUT, false ),
					description_override: 'Add a translation of a product, as a draft customers cannot see. Prices, SKU and stock come from the original. A language that already has a translation is refused. Publish it with publish_product_translation. A person retranslating it in WPML\'s own editor, or automatic translation, can overwrite this text.',
					forced_params: [
						'status' => 'draft',
						...self::PRODUCT_WRITE,
					],
					policy: new NewTranslationPolicy( $elements ),
					declared_arguments: self::NEW_TRANSLATION
				),
				new OperationDescriptor(
					ToolName::from( 'update_product_translation' ),
					Operation::UpdateItem,
					new FieldProfile( [ 'id', ...self::PRODUCT_TEXT ], self::PRODUCT_OUTPUT, false ),
					description_override: 'Change the text of a product translation. Only the fields you send change, and customers see it at once if it is published. An original is refused.',
					forced_params: self::PRODUCT_WRITE,
					policy: $existing
				),
				new OperationDescriptor(
					ToolName::from( 'publish_product_translation' ),
					Operation::UpdateItem,
					new FieldProfile( [ 'id' ], self::PRODUCT_OUTPUT, false ),
					description_override: 'Publish a product translation, so customers can see and buy it in that language. An original is refused, and so is a translation whose original is not published, because that would put an unreleased product on sale.',
					forced_params: [
						'status' => 'publish',
						...self::PRODUCT_WRITE,
					],
					requires_confirmation: true,
					policy: new ExistingTranslationPolicy( $elements, original_must_be_published: true )
				),
			],
			requires_functions: self::PLUGIN_FUNCTIONS
		);
	}

	private function variations(): ResourceDescriptor {
		return new ResourceDescriptor(
			'wpml_product_variations',
			self::group(),
			RestRoute::wc( '/products/{product_id}/variations' ),
			RestRoute::wc( '/products/{product_id}/variations/{id}' ),
			'product variation',
			'variations',
			[
				new OperationDescriptor(
					ToolName::from( 'get_product_variation_translations' ),
					Operation::GetItems,
					new FieldProfile( [ 'page', 'per_page', 'include' ], [ 'id', 'description', 'attributes', 'lang', 'translations' ], false ),
					description_override: 'List the variations of a product in one language, with their text and the id of each translation. product_id is that language\'s product, and lang must be its language, because any other lists nothing. WooCommerce Multilingual creates a translated product\'s variations itself.',
					declared_arguments: [
						'lang' => [
							'type'        => 'string',
							'description' => 'The product\'s language code, such as en.',
							'required'    => true,
						],
					]
				),
				new OperationDescriptor(
					ToolName::from( 'update_product_variation_translation' ),
					Operation::UpdateItem,
					new FieldProfile( [ 'product_id', 'id', 'description' ], [ 'id', 'description', 'lang', 'translations' ], false ),
					description_override: 'Change the description of a translated variation. Its price and stock come from the original. An original is refused.',
					forced_params: self::PRODUCT_WRITE,
					policy: new ExistingTranslationPolicy( TranslatedElements::posts( 'product_variation' ) )
				),
			],
			requires_functions: self::PLUGIN_FUNCTIONS
		);
	}

	private function terms( string $route, TranslatedElements $elements, string $id, string $singular, string $plural, string $tool_noun, ?RestRoute $probe = null ): ResourceDescriptor {
		return new ResourceDescriptor(
			$id,
			self::group(),
			RestRoute::wc( $route ),
			RestRoute::wc( $route . '/{id}' ),
			$singular,
			$plural,
			[
				new OperationDescriptor(
					ToolName::from( 'get_' . $tool_noun . '_translations' ),
					Operation::GetItems,
					new FieldProfile( self::LIST_INPUT, self::TERM_OUTPUT, false ),
					description_override: sprintf( 'List %s with their language and the id of each translation. An empty translations means WPML records no language for it, and it cannot be translated until an administrator sets one.', $plural ),
					policy: new TranslationListPolicy(),
					declared_arguments: self::LANGUAGE
				),
				new OperationDescriptor(
					ToolName::from( 'create_' . $tool_noun . '_translation' ),
					Operation::CreateItem,
					new FieldProfile( self::TERM_TEXT, self::TERM_OUTPUT, false ),
					description_override: sprintf( 'Add a translation of a %s. Customers see it at once. A language that already has a translation is refused.', $singular ),
					forced_params: [ RestRequestGuards::NO_UNFILTERED_HTML => 1 ],
					policy: new NewTranslationPolicy( $elements ),
					declared_arguments: self::NEW_TRANSLATION
				),
				new OperationDescriptor(
					ToolName::from( 'update_' . $tool_noun . '_translation' ),
					Operation::UpdateItem,
					new FieldProfile( [ 'id', ...self::TERM_TEXT ], self::TERM_OUTPUT, false ),
					description_override: sprintf( 'Change the text of a %s translation. Customers see it at once. A term in the store\'s default language is refused.', $singular ),
					forced_params: [ RestRequestGuards::NO_UNFILTERED_HTML => 1 ],
					policy: new ExistingTranslationPolicy( $elements )
				),
			],
			$probe,
			$probe,
			requires_functions: self::PLUGIN_FUNCTIONS
		);
	}
}
