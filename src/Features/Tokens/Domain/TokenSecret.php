<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The secret half of a token: 32 bytes of CSPRNG output, base64url-encoded.
 * Lives only inside the create-token request; only its hash is persisted.
 */
final readonly class TokenSecret {

	private function __construct( public string $value ) {}

	public static function generate(): self {
		return new self( self::base64url( random_bytes( 32 ) ) );
	}

	public static function from_string( string $value ): self {
		return new self( $value );
	}

	/**
	 * sha256 is sufficient: the secret carries ~256 bits of entropy, so key
	 * stretching adds nothing and password_verify() would cost ~100ms per call.
	 */
	public function hash(): string {
		return hash( 'sha256', $this->value );
	}

	private static function base64url( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}
}
