<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\WooCommerceTools\Application;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Features\WooCommerceTools\Application\MetaTool;
use AgentGateMcp\Features\WooCommerceTools\Domain\MetaKeyPolicy;
use AgentGateMcp\Features\WooCommerceTools\Domain\MetaOperation;
use AgentGateMcp\Features\WooCommerceTools\Domain\MetaOwner;
use AgentGateMcp\Features\WooCommerceTools\Domain\ResourceDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestMethod;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestResult;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestRoute;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use AgentGateMcp\Shared\Exception\ToolCallException;
use AgentGateMcp\Shared\Tool\ToolGroup;
use AgentGateMcp\Tests\Doubles\FakeRestGateway;
use AgentGateMcp\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * Custom fields, read and written through the item route.
 *
 * The dispatch shape matters as much as the policy here: these tools rely on
 * WooCommerce's meta_data being an upsert, so a change that made them send the
 * whole meta array would silently start wiping every key the agent did not
 * mention. That is what most of these assertions are watching for.
 */
final class MetaToolTest extends TestCase {

	private FakeRestGateway $gateway;

	protected function setUp(): void {
		parent::setUp();

		$this->gateway = new FakeRestGateway();

		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'wp_json_encode' )->alias( static fn ( $v ): string => (string) json_encode( $v ) );
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'rest_get_server' )->justReturn( null );
	}

	private function tool( MetaOperation $operation, MetaOwner $owner = MetaOwner::Post ): MetaTool {
		$catalog = new RouteCatalog();

		return new MetaTool(
			new ResourceDescriptor(
				'products',
				ToolGroup::Products,
				RestRoute::wc( '/products' ),
				RestRoute::wc( '/products/{id}' ),
				'product',
				'products',
				[],
				null,
				null,
				$owner
			),
			$operation,
			$owner,
			ApiScope::ProductsWrite,
			new MetaKeyPolicy( 'hms_' ),
			$this->gateway,
			new RoutePermissionProbe( $catalog )
		);
	}

	public function test_tool_names_read_from_the_resource(): void {
		$this->assertSame( 'get_product_meta', $this->tool( MetaOperation::Get )->name() );
		$this->assertSame( 'set_product_meta', $this->tool( MetaOperation::Set )->name() );
		$this->assertSame( 'delete_product_meta', $this->tool( MetaOperation::Delete )->name() );
	}

	/**
	 * WooCommerce answers with WC_Meta_Data objects rather than arrays, and the
	 * REST server does not flatten them. An is_array() check on each entry
	 * therefore dropped every field and the tool returned an empty list — which
	 * looked exactly like a product with no custom fields, so nothing failed.
	 * A live store is what caught it.
	 */
	public function test_meta_entries_that_arrive_as_objects_are_still_read(): void {
		$this->gateway->will_return(
			new RestResult( [ 'meta_data' => [ (object) [ 'key' => 'supplier_ref', 'value' => 'ACME-1' ] ] ] )
		);

		$result = $this->tool( MetaOperation::Get )->execute( [ 'id' => 7 ] );

		$this->assertSame( 1, $result['count'] );
		$this->assertSame( 'supplier_ref', $result['meta'][0]['key'] );
		$this->assertSame( 'ACME-1', $result['meta'][0]['value'] );
	}

	/** Withheld silently: naming them would hand back the list the policy hides. */
	public function test_private_and_reserved_keys_are_absent_from_a_read(): void {
		$this->gateway->will_return(
			new RestResult(
				[
					'meta_data' => [
						[ 'key' => 'supplier_ref', 'value' => 'ok' ],
						[ 'key' => '_internal', 'value' => 'hidden' ],
						[ 'key' => 'hms_capabilities', 'value' => [ 'administrator' => true ] ],
						[ 'key' => 'session_tokens', 'value' => 'x' ],
					],
				]
			)
		);

		$result = $this->tool( MetaOperation::Get )->execute( [ 'id' => 7 ] );

		$this->assertSame( [ 'supplier_ref' ], array_column( $result['meta'], 'key' ) );
	}

	public function test_a_read_asks_only_for_the_meta_field(): void {
		$this->gateway->will_return( new RestResult( [ 'meta_data' => [] ] ) );

		$this->tool( MetaOperation::Get )->execute( [ 'id' => 7 ] );

		$call = $this->gateway->call();

		$this->assertSame( '/wc/v3/products/{id}', $call['route']->path_template() );
		$this->assertSame( RestMethod::Get, $call['method'] );
		$this->assertSame( 'meta_data', $call['params']['_fields'] );
	}

	/**
	 * The assertion the whole design rests on: one key goes up, not the whole
	 * meta array. WooCommerce upserts it, so every other field survives and two
	 * agents editing different keys cannot clobber each other.
	 */
	public function test_a_write_sends_only_the_one_key(): void {
		$this->tool( MetaOperation::Set )->execute(
			[
				'id'    => 7,
				'key'   => 'supplier_ref',
				'value' => 'ACME-2',
			]
		);

		$call = $this->gateway->call();

		$this->assertSame( RestMethod::Put, $call['method'] );
		$this->assertSame(
			[ [ 'key' => 'supplier_ref', 'value' => 'ACME-2' ] ],
			$call['params']['meta_data']
		);
	}

	/**
	 * WooCommerce's REST documentation says a field is deleted by sending its
	 * key with the value omitted — not with a null value.
	 */
	public function test_a_delete_sends_the_key_with_no_value_at_all(): void {
		$this->tool( MetaOperation::Delete )->execute(
			[
				'id'  => 7,
				'key' => 'supplier_ref',
			]
		);

		$entry = $this->gateway->call()['params']['meta_data'][0];

		$this->assertSame( [ 'key' => 'supplier_ref' ], $entry );
		$this->assertArrayNotHasKey( 'value', $entry );
	}

	/**
	 * Refused before dispatch, not after. A policy that only inspected the
	 * response would have already written the key.
	 *
	 * @dataProvider refused_writes
	 */
	public function test_a_refused_key_never_reaches_woocommerce( string $key, mixed $value ): void {
		$threw = false;

		try {
			$this->tool( MetaOperation::Set )->execute(
				[
					'id'    => 7,
					'key'   => $key,
					'value' => $value,
				]
			);
		} catch ( ToolCallException $e ) {
			$threw = true;
		}

		$this->assertTrue( $threw, $key . ' was accepted.' );
		$this->assertSame( [], $this->gateway->calls, $key . ' was dispatched to WooCommerce.' );
	}

	/** @return iterable<string, array{string, mixed}> */
	public static function refused_writes(): iterable {
		yield 'capabilities' => [ 'hms_capabilities', [ 'administrator' => true ] ];
		yield 'sessions'     => [ 'session_tokens', [ 'forged' => 1 ] ];
		yield 'private'      => [ '_price', '0.01' ];
		yield 'serialized'   => [ 'note', 'O:8:"stdClass":0:{}' ];
	}

	/**
	 * Deleting is its own tool, so that an agent cannot remove a field while
	 * believing it wrote one.
	 */
	public function test_setting_a_null_value_is_refused_rather_than_treated_as_a_delete(): void {
		$this->expectException( ToolCallException::class );
		$this->expectExceptionMessageMatches( '/delete_product_meta/' );

		$this->tool( MetaOperation::Set )->execute(
			[
				'id'    => 7,
				'key'   => 'supplier_ref',
				'value' => null,
			]
		);
	}

	public function test_a_missing_key_is_refused(): void {
		$this->expectException( ToolCallException::class );

		$this->tool( MetaOperation::Delete )->execute( [ 'id' => 7 ] );
	}

	/** Customer meta says out loud that it is attached to an account. */
	public function test_a_user_owned_write_warns_about_the_account(): void {
		$this->assertStringContainsString(
			'WordPress user account',
			$this->tool( MetaOperation::Set, MetaOwner::User )->description()
		);
	}
}
