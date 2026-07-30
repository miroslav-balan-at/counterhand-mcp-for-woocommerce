<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\ToolIntent;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Tests\Unit\TestCase;

/**
 * Knowing the operation is enough to know the HTTP method, the route and
 * whether it writes. Descriptors therefore never state any of the three, which
 * is what makes it impossible to declare a "delete" that quietly dispatches a
 * GET — the class of bug no test could catch after the fact.
 */
final class OperationTest extends TestCase {

	/**
	 * @dataProvider dispatch
	 */
	public function test_each_operation_dispatches_the_method_rest_expects(
		Operation $operation,
		RestMethod $method,
		bool $targets_item
	): void {
		$this->assertSame( $method, $operation->method() );
		$this->assertSame( $targets_item, $operation->targets_item() );
	}

	/** @return \Generator<string, array{Operation, RestMethod, bool}> */
	public static function dispatch(): \Generator {
		yield 'get_items'   => [ Operation::GetItems, RestMethod::Get, false ];
		yield 'get_item'    => [ Operation::GetItem, RestMethod::Get, true ];
		yield 'create_item' => [ Operation::CreateItem, RestMethod::Post, false ];
		yield 'update_item' => [ Operation::UpdateItem, RestMethod::Put, true ];
		yield 'delete_item' => [ Operation::DeleteItem, RestMethod::Delete, true ];
	}

	public function test_only_reads_are_read_intent(): void {
		$this->assertSame( ToolIntent::Read, Operation::GetItems->intent() );
		$this->assertSame( ToolIntent::Read, Operation::GetItem->intent() );

		foreach ( [ Operation::CreateItem, Operation::UpdateItem, Operation::DeleteItem ] as $write ) {
			$this->assertSame( ToolIntent::Write, $write->intent() );
		}
	}

	/**
	 * The shape of the response an agent gets back. A create returns the one
	 * thing it made, not a list of one.
	 */
	public function test_only_listing_returns_a_collection(): void {
		$this->assertTrue( Operation::GetItems->returns_collection() );

		foreach ( [ Operation::GetItem, Operation::CreateItem, Operation::UpdateItem, Operation::DeleteItem ] as $single ) {
			$this->assertFalse( $single->returns_collection() );
		}
	}

	public function test_the_case_values_are_woocommerces_own_controller_method_names(): void {
		$this->assertSame(
			[ 'get_items', 'get_item', 'create_item', 'update_item', 'delete_item' ],
			array_map( static fn ( Operation $operation ): string => $operation->value, Operation::cases() )
		);
	}

	public function test_a_description_is_written_in_the_resources_own_nouns(): void {
		$this->assertStringContainsString( 'coupons', Operation::GetItems->describe( 'coupon', 'coupons' ) );
		$this->assertStringContainsString( 'coupon', Operation::GetItem->describe( 'coupon', 'coupons' ) );
	}

	/** An agent that cannot tell trashing from erasing will eventually erase. */
	public function test_the_delete_description_says_what_force_does(): void {
		$this->assertStringContainsString( 'force', Operation::DeleteItem->describe( 'coupon', 'coupons' ) );
	}
}
