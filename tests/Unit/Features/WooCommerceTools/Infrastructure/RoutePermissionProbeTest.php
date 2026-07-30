<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use Counterhand\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use Counterhand\Tests\Doubles\FakeRestServer;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The capability gate. Every assertion here is about failing closed: the probe
 * decides whether a tool is offered at all, so anything it cannot establish
 * affirmatively has to come back false.
 */
final class RoutePermissionProbeTest extends TestCase {

	private function probe( FakeRestServer $server ): RoutePermissionProbe {
		// RouteCatalog refuses to build before init, which is what keeps it from
		// forcing rest_api_init early in production.
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'rest_get_server' )->justReturn( $server );

		return new RoutePermissionProbe( new RouteCatalog() );
	}

	private function coupons(): RestRoute {
		return RestRoute::wc( '/coupons' );
	}

	public function test_a_route_this_site_does_not_serve_is_denied(): void {
		$probe = $this->probe( new FakeRestServer() );

		$this->assertFalse( $probe->allows( $this->coupons(), RestMethod::Get ) );
	}

	public function test_a_method_the_route_does_not_answer_is_denied(): void {
		$probe = $this->probe(
			FakeRestServer::serving( '/wc/v3/coupons', 'GET', static fn (): bool => true )
		);

		$this->assertTrue( $probe->allows( $this->coupons(), RestMethod::Get ) );
		$this->assertFalse( $probe->allows( $this->coupons(), RestMethod::Post ) );
	}

	public function test_a_route_registered_without_a_usable_callback_is_denied(): void {
		$probe = $this->probe(
			FakeRestServer::serving( '/wc/v3/coupons', 'GET', null )
		);

		$this->assertFalse( $probe->allows( $this->coupons(), RestMethod::Get ) );
	}

	public function test_a_denying_callback_is_denied(): void {
		$probe = $this->probe(
			FakeRestServer::serving( '/wc/v3/coupons', 'GET', static fn (): bool => false )
		);

		$this->assertFalse( $probe->allows( $this->coupons(), RestMethod::Get ) );
	}

	/**
	 * Permission callbacks return a WP_Error carrying the reason, and every one
	 * of those is truthy. Only an identical true counts as permission.
	 */
	public function test_a_truthy_non_true_return_is_denied(): void {
		foreach ( [ 'yes', 1, [ 'allowed' ], new \stdClass() ] as $truthy ) {
			$probe = $this->probe(
				FakeRestServer::serving( '/wc/v3/coupons', 'GET', static fn (): mixed => $truthy )
			);

			$this->assertFalse( $probe->allows( $this->coupons(), RestMethod::Get ) );
		}
	}

	public function test_an_allowing_callback_is_allowed(): void {
		$probe = $this->probe(
			FakeRestServer::serving( '/wc/v3/coupons', 'GET', static fn (): bool => true )
		);

		$this->assertTrue( $probe->allows( $this->coupons(), RestMethod::Get ) );
	}

	public function test_the_callback_sees_the_method_route_args_and_defaults(): void {
		$seen = null;
		$args = [
			'context'  => [
				'type'    => 'string',
				'default' => 'view',
			],
			'per_page' => [ 'type' => 'integer' ],
		];

		$probe = $this->probe(
			FakeRestServer::serving(
				'/wc/v3/coupons',
				'POST',
				static function ( \WP_REST_Request $request ) use ( &$seen ): bool {
					$seen = $request;

					return true;
				},
				$args
			)
		);

		$probe->allows( $this->coupons(), RestMethod::Post );

		$this->assertInstanceOf( \WP_REST_Request::class, $seen );
		$this->assertSame( 'POST', $seen->get_method() );
		$this->assertSame( '/wc/v3/coupons', $seen->get_route() );
		$this->assertSame( [ 'args' => $args ], $seen->get_attributes() );

		// Only 'context' declares a default, and the router hands over exactly
		// the declared ones — this is what makes $request['context'] readable.
		$this->assertSame( [ 'context' => 'view' ], $seen->get_default_params() );
		$this->assertSame( 'view', $seen['context'] );
	}

	public function test_the_answer_is_memoized_per_route_and_method(): void {
		$calls = 0;

		$probe = $this->probe(
			new FakeRestServer(
				[
					'/wc/v3/coupons' => [
						[
							'methods'             => [
								'GET'  => true,
								'POST' => true,
							],
							'args'                => [],
							'permission_callback' => static function () use ( &$calls ): bool {
								++$calls;

								return true;
							},
						],
					],
				]
			)
		);

		$probe->allows( $this->coupons(), RestMethod::Get );
		$probe->allows( $this->coupons(), RestMethod::Get );
		$this->assertSame( 1, $calls );

		// Same handler, but read and write permission can differ, so the two
		// methods must not share an answer.
		$probe->allows( $this->coupons(), RestMethod::Post );
		$this->assertSame( 2, $calls );
	}

	/** A denial is an answer too — re-asking a denied route on every tool would be the expensive case. */
	public function test_a_denial_is_memoized_as_well(): void {
		$calls = 0;

		$probe = $this->probe(
			FakeRestServer::serving(
				'/wc/v3/coupons',
				'GET',
				static function () use ( &$calls ): bool {
					++$calls;

					return false;
				}
			)
		);

		$probe->allows( $this->coupons(), RestMethod::Get );
		$probe->allows( $this->coupons(), RestMethod::Get );

		$this->assertSame( 1, $calls );
	}

	public function test_the_route_table_is_read_once_however_many_routes_are_probed(): void {
		$server = FakeRestServer::serving( '/wc/v3/coupons', 'GET', static fn (): bool => true );
		$probe  = $this->probe( $server );

		$probe->allows( $this->coupons(), RestMethod::Get );
		$probe->allows( $this->coupons(), RestMethod::Post );
		$probe->allows( RestRoute::wc( '/products' ), RestMethod::Get );

		$this->assertSame( 1, $server->get_routes_calls );
	}
}
