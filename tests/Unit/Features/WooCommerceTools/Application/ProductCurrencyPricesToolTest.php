<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Application;

use Counterhand\Features\WooCommerceTools\Application\ProductCurrencyPricesTool;
use Counterhand\Features\WooCommerceTools\Infrastructure\MultiCurrencyInterface;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestGatewayInterface;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestResult;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use Counterhand\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Unit\TestCase;

final class ProductCurrencyPricesToolTest extends TestCase {

	/** @return ProductCurrencyPricesTool The write. */
	private function write_tool( bool $variable = false ): ProductCurrencyPricesTool {
		$currency = $this->createStub( MultiCurrencyInterface::class );
		$currency->method( 'currencies' )->willReturn( [ 'EUR' ] );
		$currency->method( 'original_product_id' )->willReturn( 5 );
		$currency->method( 'is_variable' )->willReturn( $variable );

		$gateway = $this->createMock( RestGatewayInterface::class );
		$gateway->expects( $this->never() )->method( 'dispatch' );

		return ProductCurrencyPricesTool::both( $currency, $gateway, new RoutePermissionProbe( new RouteCatalog() ) )[1];
	}

	/**
	 * What the route would answer success to, and ignore.
	 *
	 * @dataProvider refused
	 * @param array<mixed> $prices
	 */
	public function test_what_the_route_would_ignore_is_refused_before_it_is_sent( array $prices, bool $variable ): void {
		$this->expectException( ToolCallException::class );

		$this->write_tool( $variable )->execute(
			[
				'id'     => 5,
				'prices' => $prices,
			]
		);
	}

	/** @return array<string, array{array<mixed>, bool}> */
	public static function refused(): array {
		return [
			'no currency'        => [ [], false ],
			'not set up'         => [ [ 'XXX' => [ 'regular_price' => '1' ] ], false ],
			'a variable product' => [ [ 'EUR' => [ 'regular_price' => '1' ] ], true ],
			'not a number'       => [ [ 'EUR' => [ 'regular_price' => 'free' ] ], false ],
			'a bad sale price'   => [
				[
					'EUR' => [
						'regular_price' => '1',
						'sale_price'    => 'x',
					],
				],
				false,
			],
		];
	}

	public function test_it_sits_under_the_products_scopes(): void {
		$tool = $this->write_tool();

		$this->assertSame( ToolGroup::Products, $tool->group() );
		$this->assertSame( ToolGroup::Products->write_scope(), $tool->required_scope() );
	}
}
