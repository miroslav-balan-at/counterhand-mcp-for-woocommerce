<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Tokens\Domain;

use Counterhand\Features\Tokens\Domain\IssuedTokens;
use Counterhand\Features\Tokens\Domain\PlainRefreshToken;
use Counterhand\Features\Tokens\Domain\PlainToken;
use Counterhand\Features\Tokens\Domain\SealedRotation;
use Counterhand\Features\Tokens\Domain\TokenId;
use Counterhand\Features\Tokens\Domain\TokenSecret;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * @covers \Counterhand\Features\Tokens\Domain\SealedRotation
 */
final class SealedRotationTest extends TestCase {

	private TokenId $token_id;
	private IssuedTokens $issued;

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$this->token_id = TokenId::generate();
		$this->issued   = new IssuedTokens(
			PlainToken::compose( $this->token_id, TokenSecret::generate() ),
			new \DateTimeImmutable( '@1795700000' ),
			PlainRefreshToken::compose( $this->token_id, TokenSecret::generate() )
		);
	}

	public function test_the_retired_refresh_secret_opens_the_same_pair(): void {
		$retired = TokenSecret::generate();

		$opened = SealedRotation::seal( $this->issued, $retired )->open( $this->token_id, $retired );

		self::assertNotNull( $opened );
		self::assertSame( $this->issued->access->to_string(), $opened->access->to_string() );
		self::assertSame( $this->issued->refresh?->to_string(), $opened->refresh?->to_string() );
		self::assertSame( 1795700000, $opened->access_expires_at->getTimestamp() );
	}

	public function test_any_other_secret_opens_nothing(): void {
		$sealed = SealedRotation::seal( $this->issued, TokenSecret::generate() );

		self::assertNull( $sealed->open( $this->token_id, TokenSecret::generate() ) );
	}

	public function test_the_stored_value_holds_no_secret_in_the_clear(): void {
		$sealed = SealedRotation::seal( $this->issued, TokenSecret::generate() )->sealed;

		self::assertStringNotContainsString( $this->issued->access->secret->value, $sealed );
		self::assertStringNotContainsString( (string) $this->issued->refresh?->secret->value, $sealed );
	}

	public function test_a_damaged_value_opens_nothing(): void {
		$retired = TokenSecret::generate();
		$sealed  = SealedRotation::seal( $this->issued, $retired )->sealed;

		self::assertNull( SealedRotation::from_string( substr( $sealed, 0, -4 ) . 'AAAA' )->open( $this->token_id, $retired ) );
		self::assertNull( SealedRotation::from_string( 'not base64 !' )->open( $this->token_id, $retired ) );
		self::assertNull( SealedRotation::from_string( '' )->open( $this->token_id, $retired ) );
	}
}
