<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The refresh token handed to a client once per rotation.
 * Format: counterhand_refresh_{token_id}_{secret} — the middle segment keeps it
 * from ever parsing as an access token.
 */
final readonly class PlainRefreshToken {

	public const PREFIX = 'counterhand_refresh';

	private function __construct(
		public TokenId $token_id,
		public TokenSecret $secret,
	) {}

	public static function compose( TokenId $token_id, TokenSecret $secret ): self {
		return new self( $token_id, $secret );
	}

	public function to_string(): string {
		return sprintf( '%s_%s_%s', self::PREFIX, $this->token_id->value, $this->secret->value );
	}

	/** @return array{0: TokenId, 1: TokenSecret}|null */
	public static function parse( string $raw ): ?array {
		if ( 1 !== preg_match( '/^counterhand_refresh_([a-z0-9]{16})_([A-Za-z0-9_-]{43})$/', $raw, $matches ) ) {
			return null;
		}

		$token_id = TokenId::try_from_string( $matches[1] );
		if ( null === $token_id ) {
			return null;
		}

		return [ $token_id, TokenSecret::from_string( $matches[2] ) ];
	}
}
