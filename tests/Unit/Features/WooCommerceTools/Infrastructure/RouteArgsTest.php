<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RouteArgs;
use Counterhand\Tests\Unit\TestCase;

final class RouteArgsTest extends TestCase {

	public function test_names_are_reported_in_the_order_woocommerce_declared_them(): void {
		$args = $this->args(
			[
				'code'   => [ 'type' => 'string' ],
				'amount' => [ 'type' => 'string' ],
			]
		);

		$this->assertSame( [ 'code', 'amount' ], $args->names() );
	}

	/**
	 * A route with no arguments still exists and still dispatches — worth
	 * telling apart from a route that is absent, which hides its tool.
	 */
	public function test_a_route_can_legitimately_declare_no_arguments(): void {
		$this->assertFalse( $this->args( [] )->declares_args() );
		$this->assertSame( [], $this->args( [] )->names() );
	}

	public function test_a_route_with_arguments_says_so(): void {
		$this->assertTrue( $this->args( [ 'code' => [ 'type' => 'string' ] ] )->declares_args() );
	}

	/** Mirrors WP_REST_Server: only args that declare a default contribute one. */
	public function test_defaults_are_collected_from_the_arguments_that_declare_them(): void {
		$args = $this->args(
			[
				'context'  => [
					'type'    => 'string',
					'default' => 'view',
				],
				'per_page' => [
					'type'    => 'integer',
					'default' => 10,
				],
				'search'   => [ 'type' => 'string' ],
			]
		);

		$this->assertSame(
			[
				'context'  => 'view',
				'per_page' => 10,
			],
			$args->defaults()
		);
	}

	/**
	 * isset() is core's own test here, and it treats a null default as no
	 * default. Matching that matters: a null handed to set_default_params()
	 * would shadow a value the callback expects to resolve elsewhere.
	 */
	public function test_a_null_default_is_not_a_default(): void {
		$args = $this->args( [ 'parent' => [ 'default' => null ] ] );

		$this->assertSame( [], $args->defaults() );
	}

	public function test_a_malformed_argument_spec_contributes_nothing(): void {
		$args = $this->args( [ 'code' => 'not-a-spec' ] );

		$this->assertSame( [], $args->defaults() );
	}

	/** @param array<string, mixed> $args */
	private function args( array $args ): RouteArgs {
		return new RouteArgs( '/wc/v3/coupons', RestMethod::Post, $args );
	}
}
