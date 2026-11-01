<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Application;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\Tokens\Domain\ScopeGroupInterface;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Features\WooCommerceTools\Descriptors\GermanizedDescriptors;
use Counterhand\Features\WooCommerceTools\Domain\TranslatableMetaRules;
use Counterhand\Features\WooCommerceTools\Domain\TranslatedElements;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestGatewayInterface;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRequestGuards;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * A product translation's custom fields, next to the original's.
 *
 * MetaTool refuses protected keys, and almost every field WPML translates is one, such
 * as _purchase_note and Germanized's _mini_desc. So this has its own, narrower rule.
 * WooCommerce's meta_data also hides fields it counts as internal, so values are read
 * from the post once the route has answered for the permission.
 */
final readonly class ProductTranslationMetaTool implements ToolInterface {

	private const GET_NAME = 'get_product_translation_fields';

	private const SET_NAME = 'set_product_translation_field';

	private function __construct(
		private string $tool_name,
		private TranslatableMetaRules $rules,
		private IntegrationGroup $group,
		private RestGatewayInterface $gateway,
		private RoutePermissionProbe $probe,
	) {}

	/** @return list<self> */
	public static function both( TranslatableMetaRules $rules, IntegrationGroup $group, RestGatewayInterface $gateway, RoutePermissionProbe $probe ): array {
		return [
			new self( self::GET_NAME, $rules, $group, $gateway, $probe ),
			new self( self::SET_NAME, $rules, $group, $gateway, $probe ),
		];
	}

	public function name(): string {
		return $this->tool_name;
	}

	public function description(): string {
		return $this->is_read_only()
			? 'List the custom text fields WPML translates on a product translation, such as its purchase note and Germanized\'s details, each with the original\'s text and the translation\'s. An empty translation is one still to do. id is the translation. SEO titles and descriptions are not here: set them on the translation through the Yoast or Rank Math integration.'
			: 'Set one custom text field on a product translation, by key from get_product_translation_fields. HTML is limited to what a post may contain. An original is refused.';
	}

	public function input_schema(): array {
		$properties = [
			'id' => [
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => 'Id of the product translation.',
			],
		];

		if ( ! $this->is_read_only() ) {
			$properties['key']   = [ 'type' => 'string' ];
			$properties['value'] = [
				'type'        => 'string',
				'description' => 'The translated text.',
			];
		}

		return [
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => array_keys( $properties ),
			'additionalProperties' => false,
		];
	}

	public function required_scope(): ScopeInterface {
		return $this->is_read_only() ? $this->group->read_scope() : ( $this->group->write_scope() ?? $this->group->read_scope() );
	}

	public function group(): ScopeGroupInterface {
		return $this->group;
	}

	public function is_available(): bool {
		return function_exists( 'wpml_element_language_details_filter' )
			&& function_exists( 'wcml_is_multi_currency_on' )
			&& $this->probe->allows( RestRoute::wc( '/products' ), $this->is_read_only() ? RestMethod::Get : RestMethod::Post );
	}

	public function requires_confirmation(): bool {
		return false;
	}

	public function is_read_only(): bool {
		return self::GET_NAME === $this->tool_name;
	}

	public function is_destructive(): bool {
		return false;
	}

	public function is_idempotent(): bool {
		return true;
	}

	public function is_open_world(): bool {
		return false;
	}

	public function execute( array $arguments ): array {
		$id       = (int) ( $arguments['id'] ?? 0 );
		$elements = TranslatedElements::posts( 'product' );
		$details  = $elements->details( $id );

		if ( null === $details || ! $elements->is_translation( $details ) ) {
			throw new ToolCallException( 'id must be a translation. An original cannot be changed through these tools.' );
		}

		return $this->is_read_only()
			? $this->read( $id, $elements->id_in( $details, (string) $details->source_language_code ) )
			: $this->write( $id, (string) ( $arguments['key'] ?? '' ), (string) ( $arguments['value'] ?? '' ) );
	}

	/** @return array<string, mixed> */
	private function read( int $id, ?int $original ): array {
		$this->gateway->dispatch(
			RestRoute::wc( '/products/{id}' ),
			RestMethod::Get,
			[
				'id'      => $id,
				'_fields' => 'id',
			]
		);

		$fields = [];

		foreach ( $this->rules->keys() as $key ) {
			$text = null === $original ? '' : get_post_meta( $original, $key, true );

			if ( is_string( $text ) && '' !== $text ) {
				$translation = get_post_meta( $id, $key, true );
				$fields[]    = [
					'key'         => $key,
					'original'    => $text,
					'translation' => is_string( $translation ) ? $translation : '',
				];
			}
		}

		return [
			'fields' => $fields,
			'count'  => count( $fields ),
		];
	}

	/**
	 * @return array<string, mixed>
	 * @throws ToolCallException When the key is not a translated text field, or the value did not persist.
	 */
	private function write( int $id, string $key, string $value ): array {
		if ( ! in_array( $key, $this->rules->keys(), true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( sprintf( '"%s" is not a translated text field. Use a key from get_product_translation_fields.', $key ) );
		}

		if ( is_serialized( $value ) ) {
			throw new ToolCallException( 'A serialized value cannot be stored as translated text.' );
		}

		$text = wp_kses_post( $value );

		$this->gateway->dispatch(
			RestRoute::wc( '/products/{id}' ),
			RestMethod::Put,
			[
				'id'                                  => $id,
				'meta_data'                           => [
					[
						'key'   => $key,
						'value' => $text,
					],
				],
				RestRequestGuards::NO_UNFILTERED_HTML => 1,
				...GermanizedDescriptors::KEEP_AGE_CHECK,
				'_fields'                             => 'id',
			]
		);

		if ( get_post_meta( $id, $key, true ) !== $text ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- as above.
			throw new ToolCallException( sprintf( 'WooCommerce did not store "%s". A plugin may be managing it.', $key ) );
		}

		return [
			'id'    => $id,
			'key'   => $key,
			'value' => $text,
		];
	}
}
