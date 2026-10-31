<?php

declare( strict_types=1 );

namespace Counterhand\Features\Abilities;

use Counterhand\Features\WooCommerceTools\Domain\ArgumentPolicy;
use Counterhand\Features\WooCommerceTools\Domain\Verdict;

defined( 'ABSPATH' ) || exit;

/**
 * Rank Math expands %customfield(key)% with get_post_meta and no check for protected
 * keys. Written into a post type's title template it publishes that key in the <title>
 * of every product, past MetaKeyPolicy and any field a plugin keeps private.
 *
 * Only the variables whose argument cannot name data are allowed to take one, so a
 * variable added by a later release is refused until someone has read what it does.
 * Rank Math saves through sanitize_text_field and strips tags again when rendering, so
 * %custom%aafield(x)% and %custom<b>field(x)% count as what they become.
 */
final readonly class RankMathFieldPolicy implements ArgumentPolicy {

	private const DATA_VARIABLE = '/%(?!(?:date|modified|currenttime|categories|tags)\()[a-z0-9_-]+\(/i';

	public function verdict( array $arguments ): Verdict {
		$found = false;

		array_walk_recursive(
			$arguments,
			static function ( mixed $value ) use ( &$found ): void {
				$found = $found || ( is_string( $value ) && self::reads_private_data( $value ) );
			}
		);

		return $found
			? Verdict::deny( 'Rank Math variables that take an argument, such as %customfield(...)% and %customterm(...)%, cannot be written here, because they could expose private data. Only %date(...)%, %modified(...)%, %currenttime(...)%, %categories(...)% and %tags(...)% can. An administrator can set others in Rank Math directly.' )
			: Verdict::allow();
	}

	private static function reads_private_data( string $value ): bool {
		foreach ( [ $value, wp_strip_all_tags( $value ), sanitize_text_field( $value ) ] as $form ) {
			if ( 1 === preg_match( self::DATA_VARIABLE, $form ) ) {
				return true;
			}
		}

		return false;
	}
}
