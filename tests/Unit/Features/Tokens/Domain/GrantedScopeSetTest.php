<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\Tokens\Domain;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Features\Tokens\Domain\GrantedScopeSet;
use AgentGateMcp\Tests\Unit\TestCase;

final class GrantedScopeSetTest extends TestCase {

	public function test_unknown_scopes_are_silently_dropped(): void {
		$scope_set = GrantedScopeSet::from_csv( 'products:read,hack:everything,orders:write,,garbage' );

		self::assertTrue( $scope_set->contains( ApiScope::ProductsRead ) );
		self::assertTrue( $scope_set->contains( ApiScope::OrdersWrite ) );
		self::assertCount( 2, $scope_set->all() );
	}

	public function test_write_never_implies_read(): void {
		$scope_set = GrantedScopeSet::from_csv( 'products:write' );

		self::assertTrue( $scope_set->contains( ApiScope::ProductsWrite ) );
		self::assertFalse( $scope_set->contains( ApiScope::ProductsRead ) );
	}

	public function test_empty_and_garbage_csv_yield_empty_set(): void {
		self::assertTrue( GrantedScopeSet::from_csv( '' )->is_empty() );
		self::assertTrue( GrantedScopeSet::from_csv( 'nope,also:nope' )->is_empty() );
	}

	public function test_duplicates_are_deduplicated(): void {
		$scope_set = GrantedScopeSet::from_csv( 'orders:read,orders:read, orders:read' );

		self::assertCount( 1, $scope_set->all() );
	}

	public function test_csv_roundtrip(): void {
		$original = GrantedScopeSet::from_values( [ 'products:read', 'reports:read' ] );

		$restored = GrantedScopeSet::from_csv( $original->to_csv() );

		self::assertSame( $original->to_csv(), $restored->to_csv() );
	}
}
