<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RouteArgs;
use Counterhand\Features\WooCommerceTools\Infrastructure\RouteIndexer;
use Counterhand\Tests\Unit\TestCase;

/**
 * The join between what descriptors declare and what WordPress registered. If
 * a template fails to match, the tool silently disappears from tools/list — so
 * every shape WordPress' route table can take is worth pinning here.
 */
final class RouteIndexerTest extends TestCase {

	private RouteIndexer $indexer;

	protected function setUp(): void {
		parent::setUp();
		$this->indexer = new RouteIndexer();
	}

	public function test_regex_placeholders_become_descriptor_templates(): void {
		$index = $this->indexer->index(
			[ '/wc/v3/products/(?P<id>[\d]+)' => [ $this->handler( [ 'GET' => true ] ) ] ]
		);

		$this->assertArrayHasKey( '/wc/v3/products/{id}', $index );
	}

	public function test_every_placeholder_on_a_nested_route_is_normalized(): void {
		$index = $this->indexer->index(
			[
				'/wc/v3/orders/(?P<order_id>[\d]+)/notes/(?P<id>[\d]+)' => [ $this->handler( [ 'GET' => true ] ) ],
			]
		);

		$this->assertArrayHasKey( '/wc/v3/orders/{order_id}/notes/{id}', $index );
	}

	public function test_a_route_without_placeholders_is_left_alone(): void {
		$index = $this->indexer->index( [ '/wc/v3/products' => [ $this->handler( [ 'GET' => true ] ) ] ] );

		$this->assertArrayHasKey( '/wc/v3/products', $index );
	}

	/**
	 * WordPress registers EDITABLE as POST, PUT and PATCH on a single handler.
	 * A descriptor asking for PUT has to find it.
	 */
	public function test_a_handler_is_indexed_under_each_verb_it_accepts(): void {
		$index = $this->indexer->index(
			[
				'/wc/v3/coupons/(?P<id>[\d]+)' => [
					$this->handler(
						[
							'POST'  => true,
							'PUT'   => true,
							'PATCH' => true,
						]
					),
				],
			]
		);

		$this->assertSame( [ 'POST', 'PUT' ], array_keys( $index['/wc/v3/coupons/{id}'] ) );
	}

	/** PATCH and OPTIONS are real routes; they are just not ones this plugin dispatches. */
	public function test_verbs_outside_the_dispatchable_four_are_skipped(): void {
		$index = $this->indexer->index(
			[ '/wc/v3/products' => [ $this->handler( [ 'OPTIONS' => true ] ) ] ]
		);

		$this->assertSame( [], $index );
	}

	/**
	 * The router dispatches to the first handler matching a method, so a second
	 * registration is unreachable code. Publishing its schema would describe an
	 * endpoint that never runs.
	 */
	public function test_the_first_handler_registered_for_a_method_wins(): void {
		$index = $this->indexer->index(
			[
				'/wc/v3/products' => [
					$this->handler( [ 'GET' => true ], [ 'first' => [ 'type' => 'string' ] ] ),
					$this->handler( [ 'GET' => true ], [ 'second' => [ 'type' => 'string' ] ] ),
				],
			]
		);

		$this->assertSame( [ 'first' ], $index['/wc/v3/products']['GET']->names() );
	}

	public function test_the_indexed_entry_carries_its_own_template_and_method(): void {
		$index = $this->indexer->index(
			[ '/wc/v3/coupons/(?P<id>[\d]+)' => [ $this->handler( [ 'DELETE' => true ] ) ] ]
		);
		$args  = $index['/wc/v3/coupons/{id}']['DELETE'];

		$this->assertInstanceOf( RouteArgs::class, $args );
		$this->assertSame( '/wc/v3/coupons/{id}', $args->template );
		$this->assertSame( RestMethod::Delete, $args->method );
	}

	public function test_a_handler_declaring_no_args_indexes_as_argless(): void {
		$index = $this->indexer->index( [ '/wc/v3/system_status' => [ [ 'methods' => [ 'GET' => true ] ] ] ] );

		$this->assertFalse( $index['/wc/v3/system_status']['GET']->declares_args() );
	}

	public function test_the_permission_callback_is_carried_through(): void {
		$callback = static fn (): bool => true;
		$index    = $this->indexer->index(
			[
				'/wc/v3/products' => [
					[
						'methods'             => [ 'GET' => true ],
						'permission_callback' => $callback,
					],
				],
			]
		);

		$this->assertSame( $callback, $index['/wc/v3/products']['GET']->permission_callback );
	}

	/**
	 * Legacy routes registered before permission callbacks were mandatory leave
	 * it unset, and a route can carry an uncallable value. Either way there is
	 * nothing to consult — the capability probe fails closed on null.
	 */
	public function test_an_unusable_permission_callback_becomes_null(): void {
		$index = $this->indexer->index(
			[
				'/wc/v3/legacy' => [ [ 'methods' => [ 'GET' => true ] ] ],
				'/wc/v3/broken' => [
					[
						'methods'             => [ 'GET' => true ],
						'permission_callback' => 'no_such_function_anywhere',
					],
				],
			]
		);

		$this->assertNull( $index['/wc/v3/legacy']['GET']->permission_callback );
		$this->assertNull( $index['/wc/v3/broken']['GET']->permission_callback );
	}

	/** @dataProvider malformed_tables */
	public function test_a_malformed_route_table_yields_no_entries( array $routes ): void {
		$this->assertSame( [], $this->indexer->index( $routes ) );
	}

	/** @return iterable<string, array{array<array-key, mixed>}> */
	public static function malformed_tables(): iterable {
		yield 'empty table'             => [ [] ];
		yield 'handlers not a list'     => [ [ '/wc/v3/products' => 'nonsense' ] ];
		yield 'handler not an array'    => [ [ '/wc/v3/products' => [ 'nonsense' ] ] ];
		yield 'handler without methods' => [ [ '/wc/v3/products' => [ [ 'callback' => 'noop' ] ] ] ];
		yield 'methods not an array'    => [ [ '/wc/v3/products' => [ [ 'methods' => 'GET' ] ] ] ];
	}

	public function test_args_that_are_not_an_array_are_treated_as_absent(): void {
		$index = $this->indexer->index(
			[
				'/wc/v3/products' => [
					[
						'methods' => [ 'GET' => true ],
						'args'    => 'nonsense',
					],
				],
			]
		);

		$this->assertSame( [], $index['/wc/v3/products']['GET']->args );
	}

	/**
	 * @param array<string, bool>  $methods
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function handler( array $methods, array $args = [] ): array {
		return [
			'methods'             => $methods,
			'args'                => $args,
			'permission_callback' => static fn (): bool => true,
		];
	}
}
