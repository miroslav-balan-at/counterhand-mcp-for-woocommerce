<?php

declare( strict_types=1 );

namespace Counterhand\Features\Abilities;

use Counterhand\Features\WooCommerceTools\Domain\ArgumentPolicy;
use Counterhand\Features\WooCommerceTools\Domain\Verdict;

defined( 'ABSPATH' ) || exit;

/**
 * Yoast expands %%cf_<key>%% with get_post_meta and no check for protected keys, and
 * returns the rendered result. A title of %%cf__some_key%% therefore reads any meta on
 * the post, past MetaKeyPolicy and any field a plugin keeps private, and publishes it
 * in the page's <title>. %%ct_...%% does the same with terms.
 */
final readonly class YoastFieldPolicy implements ArgumentPolicy {

	private const META_VARIABLE = '/%%\s*c[ft]_/i';

	public function verdict( array $arguments ): Verdict {
		$found = false;

		array_walk_recursive(
			$arguments,
			static function ( mixed $value ) use ( &$found ): void {
				$found = $found || ( is_string( $value ) && 1 === preg_match( self::META_VARIABLE, $value ) );
			}
		);

		if ( $found ) {
			return Verdict::deny( 'Yoast replacement variables that read custom fields or terms (%%cf_...%%, %%ct_...%%) cannot be written here, because they would expose private data. Write the text itself.' );
		}

		$canonical = $arguments['canonical'] ?? null;

		if ( is_string( $canonical ) && '' !== $canonical && wp_parse_url( $canonical, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			return Verdict::deny( 'A canonical URL pointing to another site tells search engines to index that site instead. An administrator can set one in Yoast directly.' );
		}

		return Verdict::allow();
	}
}
