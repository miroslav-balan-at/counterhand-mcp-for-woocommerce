<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * One rotation's answer, kept for the grace window so a duplicate of the
 * refresh request gets the same pair instead of an error.
 *
 * Several processes of one client share a stored credential and refresh it at
 * the same moment; the one that loses the race carries the token the winner
 * just retired. Answering it with an error makes the client drop the
 * connection, so it gets what the winner got (Okta's and Auth0's grace
 * periods behave the same way). The pair is sealed with a key derived from the
 * retired refresh secret, which only its holder has; the store keeps hashes
 * of that secret, never the secret, so a database read cannot open it.
 */
final readonly class SealedRotation {

	private const KEY_CONTEXT = 'counterhand-rotation-replay-v1';

	private function __construct( public string $sealed ) {}

	public static function from_string( string $sealed ): self {
		return new self( $sealed );
	}

	public static function seal( IssuedTokens $issued, TokenSecret $retired_refresh ): self {
		if ( null === $issued->refresh ) {
			throw new \LogicException( 'Only a rotation with a refresh token can be sealed.' );
		}

		$plain = (string) wp_json_encode(
			[
				'access'     => $issued->access->secret->value,
				'refresh'    => $issued->refresh->secret->value,
				'expires_at' => $issued->access_expires_at->getTimestamp(),
			]
		);

		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		return new self( base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::key( $retired_refresh ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- storing ciphertext as text, not obfuscation.
	}

	/** Null when the secret is not the one this was sealed with, or the value is damaged. */
	public function open( TokenId $token_id, TokenSecret $retired_refresh ): ?IssuedTokens {
		$bytes = base64_decode( $this->sealed, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- reading ciphertext stored as text, not obfuscation.
		if ( false === $bytes || strlen( $bytes ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}

		$plain = sodium_crypto_secretbox_open(
			substr( $bytes, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			substr( $bytes, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			self::key( $retired_refresh )
		);
		if ( false === $plain ) {
			return null;
		}

		$fields = json_decode( $plain, true );
		if ( ! is_array( $fields ) || ! is_string( $fields['access'] ?? null ) || ! is_string( $fields['refresh'] ?? null ) || ! is_int( $fields['expires_at'] ?? null ) ) {
			return null;
		}

		return new IssuedTokens(
			PlainToken::compose( $token_id, TokenSecret::from_string( $fields['access'] ) ),
			( new \DateTimeImmutable( '@' . $fields['expires_at'] ) )->setTimezone( new \DateTimeZone( 'UTC' ) ),
			PlainRefreshToken::compose( $token_id, TokenSecret::from_string( $fields['refresh'] ) )
		);
	}

	private static function key( TokenSecret $retired_refresh ): string {
		return sodium_crypto_generichash( self::KEY_CONTEXT, $retired_refresh->value, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}
}
