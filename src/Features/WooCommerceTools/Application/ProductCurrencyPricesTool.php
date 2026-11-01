<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Application;

use Counterhand\Features\Tokens\Domain\ScopeGroupInterface;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Features\WooCommerceTools\Descriptors\GermanizedDescriptors;
use Counterhand\Features\WooCommerceTools\Infrastructure\MultiCurrencyInterface;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestGatewayInterface;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * A product's own prices in other currencies, under the products scope because they are
 * prices, not translations.
 *
 * Not declared on update_product, because the route answers success to what it ignores:
 * a currency not set up on the store and any variable product. Multilingual returns none
 * of this in the response either.
 */
final readonly class ProductCurrencyPricesTool implements ToolInterface {

	private const GET_NAME = 'get_product_currency_prices';

	private const SET_NAME = 'set_product_currency_prices';

	private function __construct(
		private string $tool_name,
		private MultiCurrencyInterface $currency,
		private RestGatewayInterface $gateway,
		private RoutePermissionProbe $probe,
	) {}

	/** @return list<self> */
	public static function both( MultiCurrencyInterface $currency, RestGatewayInterface $gateway, RoutePermissionProbe $probe ): array {
		return [
			new self( self::GET_NAME, $currency, $gateway, $probe ),
			new self( self::SET_NAME, $currency, $gateway, $probe ),
		];
	}

	public function name(): string {
		return $this->tool_name;
	}

	public function description(): string {
		return $this->is_read_only()
			? 'Read a product\'s own prices in the store\'s other currencies. own_prices false means they are converted from the store\'s price by the exchange rate. A translation answers for its original. Offered while WooCommerce Multilingual\'s multi-currency mode is on.'
			: 'Set a product\'s own prices in the store\'s other currencies, by currency code. Currencies you leave out keep what they had, and one with no price of its own still follows the exchange rate. A currency not set up on the store and a variable product are refused. A translation sets its original\'s, which every language shares.';
	}

	public function input_schema(): array {
		$properties = [
			'id' => [
				'type'    => 'integer',
				'minimum' => 1,
			],
		];

		if ( ! $this->is_read_only() ) {
			$properties['prices'] = [
				'type'                 => 'object',
				'description'          => 'Currency code => its prices, for example {"EUR": {"regular_price": "33", "sale_price": "30"}}.',
				'additionalProperties' => [
					'type'                 => 'object',
					'properties'           => [
						'regular_price' => [ 'type' => 'string' ],
						'sale_price'    => [ 'type' => 'string' ],
					],
					'required'             => [ 'regular_price' ],
					'additionalProperties' => false,
				],
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
		return $this->is_read_only() ? ToolGroup::Products->read_scope() : ( ToolGroup::Products->write_scope() ?? ToolGroup::Products->read_scope() );
	}

	public function group(): ScopeGroupInterface {
		return ToolGroup::Products;
	}

	public function is_available(): bool {
		return $this->currency->is_on()
			&& $this->probe->allows( RestRoute::wc( '/products' ), $this->is_read_only() ? RestMethod::Get : RestMethod::Post );
	}

	public function requires_confirmation(): bool {
		return ! $this->is_read_only();
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
		$original = $this->currency->original_product_id( $id );

		if ( $original < 1 ) {
			throw new ToolCallException( 'No such product, or WPML records no language for it.' );
		}

		return $this->is_read_only() ? $this->read( $original ) : $this->write( $original, (array) ( $arguments['prices'] ?? [] ) );
	}

	private static function is_price( mixed $value ): bool {
		return is_numeric( $value ) && (float) $value >= 0;
	}

	/** @return array<string, mixed> */
	private function read( int $original ): array {
		$this->gateway->dispatch(
			RestRoute::wc( '/products/{id}' ),
			RestMethod::Get,
			[
				'id'      => $original,
				'_fields' => 'id',
			]
		);

		$prices = [];

		foreach ( $this->currency->currencies() as $code ) {
			$prices[ $code ] = $this->currency->prices( $original, $code )->to_array();
		}

		return [
			'id'         => $original,
			'own_prices' => $this->currency->has_own_prices( $original ),
			'prices'     => $prices,
		];
	}

	/**
	 * @param  array<mixed> $prices
	 * @return array<string, mixed>
	 * @throws ToolCallException When the route would ignore this, or it did not persist.
	 */
	private function write( int $original, array $prices ): array {
		if ( [] === $prices ) {
			throw new ToolCallException( 'prices must name at least one currency.' );
		}

		if ( $this->currency->is_variable( $original ) ) {
			throw new ToolCallException( 'A variable product has no prices of its own. WooCommerce Multilingual would ignore this.' );
		}

		$unknown = array_diff( array_map( 'strval', array_keys( $prices ) ), $this->currency->currencies() );

		if ( [] !== $unknown ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( sprintf( 'Not set up on this store: %s. It has: %s.', implode( ', ', $unknown ), implode( ', ', $this->currency->currencies() ) ) );
		}

		foreach ( $prices as $entry ) {
			if ( ! is_array( $entry ) || ! self::is_price( $entry['regular_price'] ?? null ) || ! ( '' === ( $entry['sale_price'] ?? '' ) || self::is_price( $entry['sale_price'] ) ) ) {
				throw new ToolCallException( 'Each currency needs a regular_price of zero or more, and sale_price must be one too, or empty.' );
			}
		}

		$this->gateway->dispatch(
			RestRoute::wc( '/products/{id}' ),
			RestMethod::Put,
			[
				'id'            => $original,
				'custom_prices' => $prices,
				'_fields'       => 'id',
				...GermanizedDescriptors::KEEP_AGE_CHECK,
			]
		);

		foreach ( $prices as $code => $entry ) {
			if ( (float) $this->currency->prices( $original, (string) $code )->regular_price !== (float) $entry['regular_price'] ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- as above.
				throw new ToolCallException( sprintf( 'WooCommerce Multilingual did not store the %s price.', (string) $code ) );
			}
		}

		return $this->read( $original );
	}
}
