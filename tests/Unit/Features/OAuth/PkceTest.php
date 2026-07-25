<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\OAuth;

use AgentGateMcp\Features\OAuth\Pkce;
use AgentGateMcp\Tests\Unit\TestCase;

final class PkceTest extends TestCase {

	public function test_valid_s256_pair_verifies(): void {
		$verifier  = 'sTv7btirx5iowAf_j9agLf5D1SnYeoftO0MmeKLaw_k';
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );

		self::assertTrue( Pkce::verify( $challenge, $verifier ) );
	}

	public function test_wrong_verifier_is_rejected(): void {
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', 'the-real-verifier', true ) ), '+/', '-_' ), '=' );

		self::assertFalse( Pkce::verify( $challenge, 'a-different-verifier' ) );
	}

	/** @dataProvider challenge_lengths */
	public function test_challenge_length_bounds( string $challenge, bool $expected ): void {
		self::assertSame( $expected, Pkce::is_valid_challenge( $challenge ) );
	}

	public static function challenge_lengths(): array {
		return [
			'too short (42)'   => [ str_repeat( 'a', 42 ), false ],
			'minimum (43)'     => [ str_repeat( 'a', 43 ), true ],
			'maximum (128)'    => [ str_repeat( 'a', 128 ), true ],
			'too long (129)'   => [ str_repeat( 'a', 129 ), false ],
			'illegal char'     => [ str_repeat( 'a', 42 ) . '!', false ],
			'empty'            => [ '', false ],
		];
	}
}
