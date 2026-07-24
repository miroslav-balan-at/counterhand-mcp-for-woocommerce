<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The public, indexable half of a token: 16 chars of [a-z0-9].
 */
final readonly class TokenId {

	private const LENGTH   = 16;
	private const ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

	private function __construct( public string $value ) {}

	public static function generate(): self {
		$id  = '';
		$max = strlen( self::ALPHABET ) - 1;

		for ( $index = 0; $index < self::LENGTH; $index++ ) {
			$id .= self::ALPHABET[ random_int( 0, $max ) ];
		}

		return new self( $id );
	}

	public static function try_from_string( string $value ): ?self {
		if ( 1 !== preg_match( '/^[a-z0-9]{16}$/', $value ) ) {
			return null;
		}

		return new self( $value );
	}
}
