<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use Counterhand\Features\WooCommerceTools\Infrastructure\SchemaProvider;
use Counterhand\Tests\Doubles\FakeRestServer;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The composition every tool leans on to answer input_schema(): find the route,
 * translate its arguments, remember the answer. Its own job is small, and the
 * two things it owns outright are the cache key and what happens when the route
 * is not there.
 */
final class SchemaProviderTest extends TestCase {

	private function provider( FakeRestServer $server ): SchemaProvider {
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'rest_get_server' )->justReturn( $server );

		return new SchemaProvider( new RouteCatalog() );
	}

	private function coupons(): RestRoute {
		return RestRoute::wc( '/coupons' );
	}

	private function server(): FakeRestServer {
		return FakeRestServer::serving(
			'/wc/v3/coupons',
			'GET',
			static fn (): bool => true,
			[
				'code'   => [ 'type' => 'string' ],
				'amount' => [ 'type' => 'string' ],
			]
		);
	}

	public function test_a_served_route_publishes_what_woocommerce_declares(): void {
		$schema = $this->provider( $this->server() )
			->schema( 'get_coupons', $this->coupons(), RestMethod::Get, FieldProfile::everything() );

		$this->assertSame( [ 'code', 'amount' ], array_keys( $schema['properties'] ) );
	}

	/**
	 * The tool is on its way to being hidden by the same missing route, so a
	 * half-built schema would help nobody. MCP still requires an object, and an
	 * object with no properties is the honest version of not knowing.
	 */
	public function test_a_route_this_site_does_not_serve_publishes_an_empty_object(): void {
		$schema = $this->provider( new FakeRestServer() )
			->schema( 'get_coupons', $this->coupons(), RestMethod::Get, FieldProfile::everything() );

		$this->assertEquals(
			[
				'type'                 => 'object',
				'properties'           => new \stdClass(),
				'additionalProperties' => false,
			],
			$schema
		);
	}

	public function test_the_path_parameters_are_carried_through_to_the_schema(): void {
		$server = FakeRestServer::serving(
			'/wc/v3/coupons/(?P<id>[\d]+)',
			'GET',
			static fn (): bool => true,
			[ 'id' => [ 'type' => 'integer' ] ]
		);

		$schema = $this->provider( $server )
			->schema( 'get_coupon', RestRoute::wc( '/coupons/{id}' ), RestMethod::Get, FieldProfile::everything(), [ 'id' ] );

		$this->assertSame( [ 'id' ], $schema['required'] );
	}

	/**
	 * Tool names are unique by construction, which is what makes them usable as
	 * the cache key — and what this proves is that the key is doing its job
	 * rather than one tool answering with another's schema.
	 */
	public function test_each_tool_gets_its_own_schema(): void {
		$provider = $this->provider( $this->server() );

		$one = $provider->schema( 'first', $this->coupons(), RestMethod::Get, new FieldProfile( [ 'code' ], [] ) );
		$two = $provider->schema( 'second', $this->coupons(), RestMethod::Get, new FieldProfile( [ 'amount' ], [] ) );

		$this->assertSame( [ 'code' ], array_keys( $one['properties'] ) );
		$this->assertSame( [ 'amount' ], array_keys( $two['properties'] ) );
	}

	/** Derivation happens once per tool per request; tools/list asks repeatedly. */
	public function test_a_schema_is_derived_once_per_tool(): void {
		$provider = $this->provider( $this->server() );
		$fields   = new FieldProfile( [ 'code' ], [] );

		$first = $provider->schema( 'get_coupons', $this->coupons(), RestMethod::Get, $fields );

		// A second ask with a different profile is answered from the memo, which
		// is only observable as the first profile's answer coming back.
		$second = $provider->schema( 'get_coupons', $this->coupons(), RestMethod::Get, new FieldProfile( [ 'amount' ], [] ) );

		$this->assertSame( $first, $second );
	}
}
