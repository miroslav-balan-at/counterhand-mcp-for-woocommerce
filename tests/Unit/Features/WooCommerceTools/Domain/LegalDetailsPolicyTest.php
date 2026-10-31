<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Brain\Monkey\Functions;
use Counterhand\Features\WooCommerceTools\Domain\LegalDetailsPolicy;
use Counterhand\Tests\Unit\TestCase;

final class LegalDetailsPolicyTest extends TestCase {

	/**
	 * @dataProvider verdicts
	 * @param array<string, mixed> $arguments
	 */
	public function test_a_term_must_exist_in_its_own_list( array $arguments, bool $allowed ): void {
		Functions\when( 'rest_do_request' )->alias(
			static function ( \WP_REST_Request $request ): object {
				$exists = in_array( $request->get_route(), [ '/wc/v3/products/delivery_times/7', '/wc/v3/products/units/8', '/wc/v3/products/deposit_types/9' ], true );

				return new class( $exists ) {
					public function __construct( private bool $exists ) {}

					public function is_error(): bool {
						return ! $this->exists;
					}
				};
			}
		);

		$this->assertSame( $allowed, ( new LegalDetailsPolicy() )->verdict( $arguments )->allowed );
	}

	/** @return iterable<string, array{array<string, mixed>, bool}> */
	public static function verdicts(): iterable {
		yield 'no term' => [ [ 'gtin' => 'x' ], true ];
		yield 'an id from its list' => [ [ 'delivery_time' => [ 'id' => 7 ] ], true ];
		yield 'clearing' => [ [ 'delivery_time' => null ], true ];
		yield 'an unknown id, which Germanized would create as a term' => [ [ 'delivery_time' => [ 'id' => 99 ] ], false ];
		yield 'an id from another list' => [ [ 'delivery_time' => [ 'id' => 8 ] ], false ];
		yield 'a name, which would clear it' => [ [ 'unit' => [ 'name' => 'kg' ] ], false ];
		yield 'clearing, as validation hands it over' => [ [ 'unit' => [] ], true ];
		yield 'an id of nothing' => [ [ 'unit' => [ 'id' => 0 ] ], false ];
		yield 'a slug, which would create a term if wrong' => [ [ 'unit' => [ 'slug' => 'kg' ] ], false ];
		yield 'a country with a known id' => [ [ 'country_specific_delivery_times' => [ [ 'country' => 'AT', 'id' => 7 ] ] ], true ];
		yield 'a country with an unknown id' => [ [ 'country_specific_delivery_times' => [ [ 'country' => 'AT', 'id' => 99 ] ] ], false ];
		yield 'clearing every country' => [ [ 'country_specific_delivery_times' => [] ], true ];
		yield 'a deposit type' => [ [ 'food' => [ 'deposit_type' => [ 'id' => 9 ] ] ], true ];
		yield 'an unknown deposit type' => [ [ 'food' => [ 'deposit_type' => [ 'id' => 99 ] ] ], false ];
		yield 'food without one' => [ [ 'food' => [ 'ingredients' => 'water' ] ], true ];
	}
}
