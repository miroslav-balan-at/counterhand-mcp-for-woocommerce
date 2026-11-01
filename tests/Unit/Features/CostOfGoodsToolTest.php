<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features;

use Brain\Monkey\Functions;
use Counterhand\Features\CostOfGoods\CostLedgerInterface;
use Counterhand\Features\CostOfGoods\CostOfGoodsCopy;
use Counterhand\Features\CostOfGoods\CostOfGoodsTool;
use Counterhand\Features\CostOfGoods\CostedLine;
use Counterhand\Features\CostOfGoods\CostedOrder;
use Counterhand\Features\CostOfGoods\CostedProduct;
use Counterhand\Features\CostOfGoods\CostOperation;
use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Tests\Unit\TestCase;

final class CostOfGoodsToolTest extends TestCase {

	private function tool( CostOperation $operation, CostLedgerInterface $ledger ): CostOfGoodsTool {
		Functions\when( '__' )->returnArg();

		return new CostOfGoodsTool( $operation, $ledger, IntegrationGroup::of( 'woocommerce-cost-of-goods', copy: new CostOfGoodsCopy() ) );
	}

	private function ledger( ?CostedProduct $product, bool $allowed = true, ?CostedOrder $order = null ): CostLedgerInterface {
		$ledger = $this->createMock( CostLedgerInterface::class );
		$ledger->method( 'user_can_read_product' )->willReturn( $allowed );
		$ledger->method( 'user_can_edit_product' )->willReturn( $allowed );
		$ledger->method( 'user_can_read_order' )->willReturn( $allowed );
		$ledger->method( 'product' )->willReturn( $product );
		$ledger->method( 'order' )->willReturn( $order );

		return $ledger;
	}

	/** One answer for a missing id and a forbidden one, so a caller cannot probe which exist. */
	public function test_a_forbidden_and_a_missing_product_answer_the_same(): void {
		$messages = [];

		foreach ( [
			$this->ledger( null ),
			$this->ledger( new CostedProduct( 'a', 'simple', '1', '1' ), false ),
		] as $ledger ) {
			try {
				$this->tool( CostOperation::GetProduct, $ledger )->execute( [ 'id' => 5 ] );
				$this->fail( 'expected a refusal' );
			} catch ( ToolCallException $exception ) {
				$messages[] = $exception->getMessage();
			}
		}

		$this->assertSame( $messages[0], $messages[1] );
	}

	/** @dataProvider bad_costs */
	public function test_a_bad_cost_or_a_variable_product_is_not_written( string $cost, string $type ): void {
		$ledger = $this->createMock( CostLedgerInterface::class );
		$ledger->method( 'user_can_edit_product' )->willReturn( true );
		$ledger->method( 'product' )->willReturn( new CostedProduct( 'a', $type, '1', null ) );
		$ledger->expects( $this->never() )->method( 'set_cost' );

		$this->expectException( ToolCallException::class );
		$this->tool( CostOperation::SetProduct, $ledger )->execute(
			[
				'id'   => 5,
				'cost' => $cost,
			]
		);
	}

	/** @return array<string, array{string, string}> */
	public static function bad_costs(): array {
		return [
			'not a number' => [ 'free', 'simple' ],
			'negative'     => [ '-1', 'simple' ],
			'variable'     => [ '5', 'variable' ],
		];
	}

	/** @param list<CostedLine> $lines */
	private function order( array $lines, string $status = 'processing', string $currency = 'EUR', float $refunded_revenue = 0.0, float $refunded_cost = 0.0, bool $unallocated = false ): CostedOrder {
		return new CostedOrder( $status, '60', $currency, 'EUR', $lines, $refunded_revenue, $refunded_cost, $unallocated, 'cancelled' !== $status );
	}

	private function line( float $total, ?float $cost ): CostedLine {
		return new CostedLine( 1, 'a', 1.0, $total, $cost );
	}

	public function test_an_order_earns_its_line_totals_less_their_costs(): void {
		$result = $this->tool( CostOperation::GetOrder, $this->ledger( null, order: $this->order( [ $this->line( 40, 15 ), $this->line( 10, 4 ) ] ) ) )->execute( [ 'id' => 9 ] );

		$this->assertEqualsWithDelta( 19.0, $result['total_cost'], 0.001 );
		$this->assertEqualsWithDelta( 31.0, $result['profit'], 0.001, 'tax and shipping are not earnings' );
	}

	public function test_a_fully_refunded_order_earns_nothing(): void {
		$result = $this->tool( CostOperation::GetOrder, $this->ledger( null, order: $this->order( [ $this->line( 40, 15 ) ], refunded_revenue: 40, refunded_cost: 15 ) ) )->execute( [ 'id' => 9 ] );

		$this->assertEqualsWithDelta( 0.0, $result['profit'], 0.001 );
	}

	/**
	 * A wrong number is worse than none.
	 *
	 * @dataProvider unknowable
	 */
	public function test_profit_is_not_stated_when_it_cannot_be_right( CostedOrder $order, string $reason ): void {
		$result = $this->tool( CostOperation::GetOrder, $this->ledger( null, order: $order ) )->execute( [ 'id' => 9 ] );

		$this->assertNull( $result['profit'] );
		$this->assertStringContainsString( $reason, (string) $result['profit_not_stated'] );
	}

	/** @return array<string, array{CostedOrder, string}> */
	public static function unknowable(): array {
		$line = new CostedLine( 1, 'a', 1.0, 40, 15 );

		return [
			'a line with no cost'  => [ new CostedOrder( 'processing', '40', 'EUR', 'EUR', [ new CostedLine( 1, 'a', 1.0, 40, null ) ], 0, 0, false, true ), 'no cost' ],
			'another currency'     => [ new CostedOrder( 'processing', '40', 'JPY', 'EUR', [ $line ], 0, 0, false, true ), 'JPY' ],
			'cancelled'            => [ new CostedOrder( 'cancelled', '40', 'EUR', 'EUR', [ $line ], 0, 0, false, false ), 'cancelled' ],
			'an amount refund'     => [ new CostedOrder( 'processing', '40', 'EUR', 'EUR', [ $line ], 0, 0, true, true ), 'amount' ],
			'nothing on the order' => [ new CostedOrder( 'processing', '0', 'EUR', 'EUR', [], 0, 0, false, true ), 'no items' ],
		];
	}
}
