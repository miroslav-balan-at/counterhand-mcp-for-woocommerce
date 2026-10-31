<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Tests\Unit\TestCase;

final class RestRouteTest extends TestCase {

	public function test_wc_routes_carry_the_wc_v3_namespace(): void {
		$route = RestRoute::wc( '/products' );

		$this->assertSame( 'wc/v3', $route->rest_namespace );
		$this->assertSame( '/wc/v3/products', $route->path_template() );
	}

	public function test_wp_routes_carry_the_wp_v2_namespace(): void {
		$this->assertSame( '/wp/v2/posts', RestRoute::wp( '/posts' )->path_template() );
	}

	/**
	 * The template form matches what WordPress' own route normalization
	 * produces, so it can double as the route-catalog key.
	 */
	public function test_path_template_keeps_placeholders_intact(): void {
		$this->assertSame(
			'/wc/v3/orders/{order_id}/notes/{id}',
			RestRoute::wc( '/orders/{order_id}/notes/{id}' )->path_template()
		);
	}

	public function test_parameters_lists_placeholders_in_declaration_order(): void {
		$route = RestRoute::wc( '/orders/{order_id}/notes/{id}' );

		$this->assertSame( [ 'order_id', 'id' ], $route->parameters() );
	}

	public function test_a_route_without_placeholders_has_no_parameters(): void {
		$this->assertSame( [], RestRoute::wc( '/products' )->parameters() );
	}

	public function test_bind_substitutes_every_placeholder(): void {
		$route = RestRoute::wc( '/orders/{order_id}/notes/{id}' );

		$this->assertSame(
			'/wc/v3/orders/42/notes/7',
			$route->bind(
				[
					'order_id' => 42,
					'id'       => 7,
					'note'     => 'ignored here',
				]
			)
		);
	}

	public function test_bind_url_encodes_path_values(): void {
		$route = RestRoute::wc( '/products/{slug}' );

		$this->assertSame( '/wc/v3/products/blue%2Fshirt', $route->bind( [ 'slug' => 'blue/shirt' ] ) );
	}

	public function test_bind_rejects_a_missing_path_value(): void {
		$this->expectException( ToolCallException::class );
		$this->expectExceptionMessage( 'Missing required "id" argument.' );

		RestRoute::wc( '/products/{id}' )->bind( [] );
	}

	public function test_bind_rejects_an_empty_path_value(): void {
		$this->expectException( ToolCallException::class );

		RestRoute::wc( '/products/{id}' )->bind( [ 'id' => '' ] );
	}

	/** A non-scalar would otherwise fatal on the string cast. */
	public function test_bind_rejects_a_non_scalar_path_value(): void {
		$this->expectException( ToolCallException::class );

		RestRoute::wc( '/products/{id}' )->bind( [ 'id' => [ 1, 2 ] ] );
	}

	public function test_strip_path_params_removes_only_placeholder_names(): void {
		$route = RestRoute::wc( '/products/{id}' );

		$this->assertSame(
			[
				'name'   => 'Hat',
				'status' => 'draft',
			],
			$route->strip_path_params(
				[
					'id'     => 9,
					'name'   => 'Hat',
					'status' => 'draft',
				]
			)
		);
	}

	public function test_strip_path_params_is_a_no_op_without_placeholders(): void {
		$params = [
			'page'     => 2,
			'per_page' => 10,
		];

		$this->assertSame( $params, RestRoute::wc( '/products' )->strip_path_params( $params ) );
	}

	public function test_a_third_party_namespace_is_a_route_like_any_other(): void {
		$route = RestRoute::of( 'contact-form-7/v1', '/contact-forms/{id}' );

		$this->assertSame( '/contact-form-7/v1/contact-forms/{id}', $route->path_template() );
		$this->assertSame( '/contact-form-7/v1/contact-forms/5', $route->bind( [ 'id' => 5 ] ) );
	}

	public function test_something_that_could_not_be_a_namespace_is_refused(): void {
		$this->expectException( \InvalidArgumentException::class );

		RestRoute::of( '../wp/v2', '/users' );
	}

	public function test_a_namespace_wordpress_allows_is_allowed_here(): void {
		$this->assertSame( '/my_plugin/v1.2/things', RestRoute::of( 'my_plugin/v1.2', '/things' )->path_template() );
	}
}
