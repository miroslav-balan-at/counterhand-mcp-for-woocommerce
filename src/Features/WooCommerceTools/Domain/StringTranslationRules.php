<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Many of these strings go through sprintf. In PHP 8 one %s too many throws
 * ArgumentCountError and a stray "50% off" throws ValueError, on whichever page shows it,
 * so a bad translation can take a page down. One lost placeholder sends customers an email
 * with no order number, and an empty string a blank subject.
 */
final readonly class StringTranslationRules {

	private const PRINTF = '/%(?:\d+\$)?[-+0\']*\d*(?:\.\d+)?[bcdeEfFgGosuxX%]/';

	private const NAMED = '/\{[a-z0-9_]+\}/i';

	/** Null when it may be saved, otherwise why not. */
	public function refusal( string $original, string $translation ): ?string {
		if ( '' === trim( $translation ) ) {
			return 'The translation is empty, and customers would see nothing there.';
		}

		foreach ( [ self::PRINTF, self::NAMED ] as $pattern ) {
			if ( self::found( $pattern, $original ) !== self::found( $pattern, $translation ) ) {
				return sprintf( 'Placeholders must match the original exactly. It has: [%s]. The translation has: [%s].', implode( ' ', self::found( $pattern, $original ) ), implode( ' ', self::found( $pattern, $translation ) ) );
			}
		}

		if ( self::stray_percents( $translation ) > self::stray_percents( $original ) ) {
			return 'A % that is not a placeholder can break the page showing this. Write it as %% if the original does, or reword it, for example "50 Prozent".';
		}

		return null;
	}

	/** @return list<string> */
	private static function found( string $pattern, string $text ): array {
		preg_match_all( $pattern, $text, $matches );
		$found = $matches[0];
		sort( $found );

		return $found;
	}

	private static function stray_percents( string $text ): int {
		return substr_count( (string) preg_replace( self::PRINTF, '', $text ), '%' );
	}
}
