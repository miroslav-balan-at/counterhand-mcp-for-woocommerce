<?php

declare( strict_types=1 );

namespace Counterhand\Features\UrlCoupons;

use Counterhand\Shared\CanonicalUri;

defined( 'ABSPATH' ) || exit;

/**
 * URL Coupons takes over every request whose path starts with a link, for every visitor,
 * and redirects them. So product would capture all of /product/..., and sale would capture
 * /sale-items/. A link must not be the start of anything the store already serves.
 */
final readonly class CouponUrlRules {

	private const FORMAT = '#^[a-z0-9][a-z0-9_-]*(/[a-z0-9][a-z0-9_-]*)*$#';

	private const RESERVED_PREFIX = '#^(wp-|wc-)#';

	private const RESERVED_SEGMENTS = [ CanonicalUri::MCP_PATH, 'xmlrpc.php', 'feed', 'oauth' ];

	private const REGEX_START = '/[\\\\(\\[.?*+^$|]/';

	/**
	 * @param  array<array-key, array<string, mixed>> $active The links in use, by coupon id.
	 * @return string|null Null when it may be used, otherwise why not.
	 */
	public function refusal( string $url, string $prefix, int $coupon_id, array $active ): ?string {
		if ( 1 !== preg_match( self::FORMAT, $url ) ) {
			return 'url must be a lowercase path such as summer-sale, with no leading slash.';
		}

		$path  = '' === $prefix ? $url : $prefix . '/' . $url;
		$first = (string) strtok( $path, '/' );

		if ( 1 === preg_match( self::RESERVED_PREFIX, $first ) || in_array( $first, [ ...self::RESERVED_SEGMENTS, rest_get_url_prefix() ], true ) ) {
			return sprintf( '"%s" belongs to WordPress or this plugin.', $path );
		}

		if ( $this->starts_something( $path ) ) {
			return sprintf( '"%s" is the start of addresses this store already uses, such as a page or a whole section, and every visitor to them would be redirected. Choose something distinctive, such as %s-offer.', $path, $path );
		}

		foreach ( $active as $id => $entry ) {
			$other = (string) ( $entry['url'] ?? '' );

			if ( (int) $id !== $coupon_id && '' !== $other && ( str_starts_with( $other, $url ) || str_starts_with( $url, $other ) ) ) {
				return sprintf( '"%s" overlaps the link of coupon %d, "%s".', $url, (int) $id, $other );
			}
		}

		return null;
	}

	public function is_public_destination( int $id ): bool {
		return in_array( get_post_type( $id ), [ 'page', 'product' ], true ) && 'publish' === get_post_status( $id );
	}

	private function starts_something( string $path ): bool {
		foreach ( array_keys( (array) get_option( 'rewrite_rules', [] ) ) as $rule ) {
			$literal = rtrim( (string) preg_split( self::REGEX_START, (string) $rule, 2 )[0], '/' );

			if ( '' !== $literal && ( str_starts_with( $literal, $path ) || str_starts_with( $path, $literal ) ) ) {
				return true;
			}
		}

		if ( 0 !== url_to_postid( home_url( '/' . $path ) ) ) {
			return true;
		}

		return $this->slug_is_taken( $path );
	}

	/**
	 * A link captures every address that starts with it, so one word captures every slug starting
	 * with that word, and a longer link the section its first word names. Term archives can sit at
	 * the root too, when a plugin removes their base, which no rewrite rule above starts with.
	 */
	private function slug_is_taken( string $path ): bool {
		global $wpdb;

		$segments = explode( '/', $path );
		$pattern  = 1 === count( $segments ) ? $wpdb->esc_like( $path ) . '%' : $wpdb->esc_like( $segments[0] );

		$post_types = array_values( get_post_types( [ 'public' => true ] ) );
		$taxonomies = array_values( get_taxonomies( [ 'public' => true ] ) );

		$post_placeholders = implode( ', ', array_fill( 0, max( 1, count( $post_types ) ), '%s' ) );
		$term_placeholders = implode( ', ', array_fill( 0, max( 1, count( $taxonomies ) ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- no core API finds posts or terms by the start of their slug; the placeholders are counted above.
		$post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name LIKE %s AND post_status = 'publish' AND post_type IN ( {$post_placeholders} ) LIMIT 1", $pattern, ...( [] !== $post_types ? $post_types : [ '' ] ) ) );
		$term = $wpdb->get_var( $wpdb->prepare( "SELECT t.term_id FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE t.slug LIKE %s AND tt.taxonomy IN ( {$term_placeholders} ) LIMIT 1", $pattern, ...( [] !== $taxonomies ? $taxonomies : [ '' ] ) ) );
		// phpcs:enable

		return null !== $post || null !== $term;
	}
}
