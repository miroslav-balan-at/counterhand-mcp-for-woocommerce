<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Brain\Monkey\Functions;
use Counterhand\Features\WooCommerceTools\Domain\UnpaidOrderPolicy;
use Counterhand\Tests\Unit\TestCase;

final class UnpaidOrderPolicyTest extends TestCase {

	public function test_an_order_waiting_for_payment_may_change(): void {
		Functions\when( 'wc_get_order' )->justReturn( $this->order( needs_payment: true ) );

		$this->assertTrue( ( new UnpaidOrderPolicy() )->verdict( [ 'id' => 5 ] )->allowed );
	}

	public function test_a_paid_order_is_refused_with_its_status(): void {
		Functions\when( 'wc_get_order' )->justReturn( $this->order( needs_payment: false ) );

		$verdict = ( new UnpaidOrderPolicy() )->verdict( [ 'id' => 5 ] );

		$this->assertFalse( $verdict->allowed );
		$this->assertStringContainsString( 'completed', $verdict->reason );
	}

	public function test_an_order_that_does_not_exist_is_refused(): void {
		Functions\when( 'wc_get_order' )->justReturn( false );

		$this->assertFalse( ( new UnpaidOrderPolicy() )->verdict( [ 'id' => 5 ] )->allowed );
	}

	public function test_no_id_is_refused_without_a_lookup(): void {
		Functions\expect( 'wc_get_order' )->never();

		$this->assertFalse( ( new UnpaidOrderPolicy() )->verdict( [] )->allowed );
	}

	private function order( bool $needs_payment ): \WC_Order {
		$order = \Mockery::mock( 'WC_Order' );
		$order->allows( 'needs_payment' )->andReturn( $needs_payment );
		$order->allows( 'get_status' )->andReturn( 'completed' );

		return $order;
	}
}
