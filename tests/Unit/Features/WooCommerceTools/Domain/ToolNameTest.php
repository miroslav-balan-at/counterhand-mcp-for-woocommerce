<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Tests\Unit\TestCase;

/**
 * The name is a published identifier an MCP client stores, and it is also what
 * lands in counterhand_action_log.tool_name — a VARCHAR(64). A name that does not fit is not
 * a validation error at the edge, it is a silently truncated audit trail.
 */
final class ToolNameTest extends TestCase {

	public function test_a_conventional_name_is_accepted(): void {
		$this->assertSame( 'get_product_variations', ToolName::from( 'get_product_variations' )->value );
	}

	public function test_a_name_reads_as_a_string_where_one_is_wanted(): void {
		$this->assertSame( 'get_coupons', (string) ToolName::from( 'get_coupons' ) );
	}

	public function test_a_name_that_would_be_truncated_by_the_log_column_is_refused(): void {
		$this->expectException( \InvalidArgumentException::class );

		ToolName::from( 'a' . str_repeat( 'b', 64 ) );
	}

	public function test_a_name_of_exactly_the_column_width_is_accepted(): void {
		$name = 'a' . str_repeat( 'b', 63 );

		$this->assertSame( 64, strlen( ToolName::from( $name )->value ) );
	}

	/**
	 * @dataProvider malformed
	 */
	public function test_a_malformed_name_is_refused( string $name ): void {
		$this->expectException( \InvalidArgumentException::class );

		ToolName::from( $name );
	}

	/** @return \Generator<string, array{string}> */
	public static function malformed(): \Generator {
		yield 'empty'         => [ '' ];
		yield 'leading digit' => [ '1_tool' ];
		yield 'leading score' => [ '_tool' ];
		yield 'uppercase'     => [ 'getCoupons' ];
		yield 'hyphen'        => [ 'get-coupons' ];
		yield 'dot'           => [ 'wc.get_coupons' ];
		yield 'space'         => [ 'get coupons' ];
	}
}
