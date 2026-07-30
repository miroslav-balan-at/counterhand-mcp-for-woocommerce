<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Playground;

use Counterhand\Features\Playground\ConnectResult;
use Counterhand\Tests\Unit\TestCase;

final class ConnectResultTest extends TestCase {

	public function test_survives_the_transient_round_trip(): void {
		$result = ConnectResult::from_array( ( new ConnectResult( true, 'Connected to Gemini.' ) )->to_array() );

		self::assertNotNull( $result );
		self::assertTrue( $result->ok );
		self::assertSame( 'Connected to Gemini.', $result->message );
	}

	/** @dataProvider invalid_payloads */
	public function test_rejects_anything_that_is_not_a_result( mixed $payload ): void {
		self::assertNull( ConnectResult::from_array( $payload ) );
	}

	public static function invalid_payloads(): array {
		return [
			'expired transient'   => [ false ],
			'null'                => [ null ],
			'string'              => [ 'ok' ],
			'array, no message'   => [ [ 'ok' => true ] ],
		];
	}
}
