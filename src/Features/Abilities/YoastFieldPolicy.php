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
 *
 * Yoast strips tags and %xx octets when it saves, so %%c%aaf_x%% is stored as the live
 * variable. Each value is judged as Yoast would store it, using its own sanitizer:
 * cf is also two hex digits, so stripping octets any other way hides the real thing.
 */
final readonly class YoastFieldPolicy implements ArgumentPolicy {

	private const META_VARIABLE = '/%%\s*c[ft]_/i';

	private const PLUGIN_SANITIZER = [ 'WPSEO_Utils', 'sanitize_text_field' ];

	/** A path on this site, and not //host or /\host, which browsers read as another host. */
	private const SITE_PATH = '#^/(?:$|[^/\\\\\s])#';

	public function verdict( array $arguments ): Verdict {
		$found = false;

		array_walk_recursive(
			$arguments,
			static function ( mixed $value ) use ( &$found ): void {
				$found = $found || ( is_string( $value ) && self::reads_private_data( $value ) );
			}
		);

		if ( $found ) {
			return Verdict::deny( 'Yoast replacement variables that read custom fields or terms (%%cf_...%%, %%ct_...%%) cannot be written here, because they would expose private data. Write the text itself.' );
		}

		$canonical = $arguments['canonical'] ?? null;

		if ( is_string( $canonical ) && '' !== $canonical && self::points_elsewhere( $canonical ) ) {
			return Verdict::deny( 'A canonical URL pointing to another site tells search engines to index that site instead. Use a full URL on this site or a path starting with /. An administrator can set any other in Yoast directly.' );
		}

		return Verdict::allow();
	}

	private static function reads_private_data( string $value ): bool {
		$sanitize = is_callable( self::PLUGIN_SANITIZER ) ? self::PLUGIN_SANITIZER : 'sanitize_text_field';

		return 1 === preg_match( self::META_VARIABLE, $value ) || 1 === preg_match( self::META_VARIABLE, (string) call_user_func( $sanitize, $value ) );
	}

	private static function points_elsewhere( string $canonical ): bool {
		return 1 !== preg_match( self::SITE_PATH, $canonical ) && self::host_of( $canonical ) !== self::host_of( home_url() );
	}

	private static function host_of( string $url ): string {
		return (string) preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
	}
}
