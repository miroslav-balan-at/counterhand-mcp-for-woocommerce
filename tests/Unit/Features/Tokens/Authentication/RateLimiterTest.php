<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Tokens\Authentication;

use Brain\Monkey\Functions;
use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Tokens\Authentication\RateLimiter;
use Counterhand\Shared\Exception\RateLimitExceededException;
use Counterhand\Tests\Unit\TestCase;

final class RateLimiterTest extends TestCase {

	private int $used = 0;

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'get_option' )->justReturn( [ 'rate_limit_per_minute' => 10 ] );
		Functions\when( 'apply_filters' )->justReturn( 10 );
		Functions\when( 'get_transient' )->alias( fn (): int => $this->used );
		Functions\when( 'set_transient' )->alias(
			function ( string $key, int $value ): bool {
				$this->used = $value;
				return true;
			}
		);
	}

	public function test_a_batch_counts_as_all_its_requests(): void {
		$limiter = new RateLimiter( new PluginSettings() );

		$limiter->hit( 'token', 9 );
		$limiter->hit( 'token' );

		self::assertSame( 10, $this->used );
	}

	public function test_a_batch_that_would_go_over_budget_is_refused_whole(): void {
		$this->used = 5;

		$this->expectException( RateLimitExceededException::class );

		try {
			( new RateLimiter( new PluginSettings() ) )->hit( 'token', 6 );
		} finally {
			self::assertSame( 5, $this->used );
		}
	}
}
