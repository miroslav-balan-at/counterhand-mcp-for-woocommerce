<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Infrastructure\SchemaCache;
use Counterhand\Tests\Unit\TestCase;

final class SchemaCacheTest extends TestCase {

	private SchemaCache $cache;

	protected function setUp(): void {
		parent::setUp();
		$this->cache = new SchemaCache();
	}

	public function test_a_schema_is_derived_once_per_tool(): void {
		$calls = 0;
		$build = function () use ( &$calls ): array {
			++$calls;

			return [ 'type' => 'object' ];
		};

		$this->assertSame( [ 'type' => 'object' ], $this->cache->remember( 'get_coupons', $build ) );
		$this->assertSame( [ 'type' => 'object' ], $this->cache->remember( 'get_coupons', $build ) );
		$this->assertSame( 1, $calls );
	}

	/**
	 * Two operations can share a route and method while surfacing different
	 * fields, so the key has to be the tool, not the endpoint — otherwise one
	 * hands the other its schema.
	 */
	public function test_tools_do_not_share_each_others_schemas(): void {
		$this->cache->remember( 'get_coupons', static fn (): array => [ 'from' => 'list' ] );

		$this->assertSame(
			[ 'from' => 'item' ],
			$this->cache->remember( 'get_coupon', static fn (): array => [ 'from' => 'item' ] )
		);
	}

	/** An empty schema is a real answer, and re-deriving it every call is the bug. */
	public function test_an_empty_schema_is_still_remembered(): void {
		$calls = 0;
		$build = function () use ( &$calls ): array {
			++$calls;

			return [];
		};

		$this->cache->remember( 'get_system_status', $build );
		$this->cache->remember( 'get_system_status', $build );

		$this->assertSame( 1, $calls );
	}
}
