<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Tokens\Domain;

use Counterhand\Features\Tokens\Domain\PlainToken;
use Counterhand\Features\Tokens\Domain\TokenId;
use Counterhand\Features\Tokens\Domain\TokenSecret;
use Counterhand\Tests\Unit\TestCase;

final class PlainTokenTest extends TestCase {

	public function test_compose_parse_roundtrip(): void {
		$token_id = TokenId::generate();
		$secret   = TokenSecret::generate();

		$raw = PlainToken::compose( $token_id, $secret )->to_string();

		$parsed = PlainToken::parse( $raw );

		self::assertNotNull( $parsed );
		self::assertSame( $token_id->value, $parsed[0]->value );
		self::assertSame( $secret->value, $parsed[1]->value );
	}

	public function test_token_has_greppable_prefix_and_expected_length(): void {
		$raw = PlainToken::compose( TokenId::generate(), TokenSecret::generate() )->to_string();

		self::assertStringStartsWith( PlainToken::PREFIX . '_', $raw );
		self::assertSame( strlen( PlainToken::PREFIX ) + 1 + 16 + 1 + 43, strlen( $raw ) );
	}

	/** @dataProvider malformed_tokens */
	public function test_parse_rejects_malformed_input( string $malformed ): void {
		self::assertNull( PlainToken::parse( $malformed ) );
	}

	public static function malformed_tokens(): array {
		$valid = PlainToken::compose( TokenId::generate(), TokenSecret::generate() )->to_string();

		return [
			'empty'            => [ '' ],
			'wrong prefix'     => [ 'wcmcp_' . substr( $valid, 6 ) ],
			'short id'         => [ 'ctrh_abc_' . str_repeat( 'A', 43 ) ],
			'short secret'     => [ 'ctrh_' . str_repeat( 'a', 16 ) . '_short' ],
			'uppercase id'     => [ 'ctrh_' . str_repeat( 'A', 16 ) . '_' . str_repeat( 'a', 43 ) ],
			'trailing garbage' => [ $valid . 'x' ],
			'sql injection'    => [ "ctrh_' OR '1'='1_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" ],
		];
	}

	public function test_generated_secrets_are_unique_and_hash_is_sha256(): void {
		$first  = TokenSecret::generate();
		$second = TokenSecret::generate();

		self::assertNotSame( $first->value, $second->value );
		self::assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $first->hash() );
		self::assertSame( hash( 'sha256', $first->value ), $first->hash() );
	}
}
