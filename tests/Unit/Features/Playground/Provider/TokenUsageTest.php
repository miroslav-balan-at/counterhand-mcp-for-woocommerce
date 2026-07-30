<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Playground\Provider;

use Counterhand\Features\Playground\Provider\TokenUsage;
use Counterhand\Tests\Unit\TestCase;

final class TokenUsageTest extends TestCase {

	public function test_starts_at_zero(): void {
		$usage = new TokenUsage();

		self::assertSame( 0, $usage->input );
		self::assertSame( 0, $usage->output );
	}

	public function test_accumulates_without_mutating(): void {
		$first = new TokenUsage( 10, 5 );
		$total = $first->plus( new TokenUsage( 3, 4 ) );

		self::assertSame( 13, $total->input );
		self::assertSame( 9, $total->output );
		self::assertSame( 10, $first->input );
		self::assertSame( 5, $first->output );
	}

	public function test_serialises_for_the_json_edge(): void {
		self::assertSame(
			[
				'input'  => 7,
				'output' => 2,
			],
			( new TokenUsage( 7, 2 ) )->to_array()
		);
	}
}
