<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\UrlCoupons;

use Brain\Monkey\Functions;
use Counterhand\Features\UrlCoupons\CouponUrlRules;
use Counterhand\Tests\Unit\TestCase;

final class CouponUrlRulesTest extends TestCase {

	/**
	 * URL Coupons matches any request starting with a link, for every visitor.
	 *
	 * @dataProvider links
	 * @param array<int, array<string, mixed>> $active
	 */
	public function test_a_link_must_not_start_anything_the_store_serves( string $url, string $prefix, array $active, bool $allowed ): void {
		global $wpdb;

		$wpdb = new class() {
			public string $posts = 'wp_posts';

			public string $terms = 'wp_terms';

			public string $term_taxonomy = 'wp_term_taxonomy';

			public function esc_like( string $text ): string {
				return $text;
			}

			public function prepare( string $query, string $like, string ...$types ): string {
				return (string) json_encode( [ str_contains( $query, 'wp_terms' ), $like ] );
			}

			public function get_var( string $prepared ): ?string {
				[ $is_term, $like ] = json_decode( $prepared, true );
				$slugs              = $is_term ? [ 'news' ] : [ 'sale-items', 'gourmet' ];

				foreach ( $slugs as $slug ) {
					if ( str_ends_with( $like, '%' ) ? str_starts_with( $slug, rtrim( $like, '%' ) ) : $slug === $like ) {
						return '1';
					}
				}

				return null;
			}
		};

		Functions\when( 'get_post_types' )->justReturn( [ 'post' => 'post', 'page' => 'page', 'product' => 'product' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [ 'category' => 'category', 'product_cat' => 'product_cat' ] );
		Functions\when( 'get_option' )->justReturn(
			[
				'product/([^/]+)/?$'          => 'x',
				'product-category/(.+?)/?$'   => 'x',
				'(.?.+?)(?:/([0-9]+))?/?$'    => 'x',
				'[^/]+/attachment/([^/]+)/?$' => 'x',
			]
		);
		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
		Functions\when( 'home_url' )->returnArg();
		Functions\when( 'url_to_postid' )->justReturn( 0 );

		$this->assertSame( $allowed, null === ( new CouponUrlRules() )->refusal( $url, $prefix, 5, $active ) );
	}

	/** @return array<string, array{string, string, array<int, array<string, mixed>>, bool}> */
	public static function links(): array {
		return [
			'distinctive'               => [ 'autumn-offer', '', [], true ],
			'a category archive at the root' => [ 'new', '', [], false ],
			'a section only by its first letters' => [ 'go/summer', '', [], true ],
			'a slug\'s own section'      => [ 'gourmet/summer', '', [], false ],
			'a whole section'           => [ 'product', '', [], false ],
			'the start of a section'    => [ 'prod', '', [], false ],
			'another section'           => [ 'product-category', '', [], false ],
			'inside a section'          => [ 'product/shirt', '', [], false ],
			'the start of a slug'       => [ 'sale', '', [], false ],
			'WordPress itself'          => [ 'wp-admin', '', [], false ],
			'WooCommerce itself'        => [ 'wc-api', '', [], false ],
			'the REST prefix'           => [ 'wp-json', '', [], false ],
			'this plugin'               => [ 'mcp', '', [], false ],
			'merely starting like feed' => [ 'feedback-offer', '', [], true ],
			'uppercase'                 => [ 'Offer', '', [], false ],
			'a leading slash'           => [ '/offer', '', [], false ],
			'going up'                  => [ '../offer', '', [], false ],
			'moved clear by the prefix' => [ 'product', 'go', [], true ],
			'another coupon has it'     => [ 'autumn-offer', '', [ 7 => [ 'url' => 'autumn-offer' ] ], false ],
			'it would capture another'  => [ 'autumn', '', [ 7 => [ 'url' => 'autumn-offer' ] ], false ],
			'its own link'              => [ 'autumn-offer', '', [ 5 => [ 'url' => 'autumn-offer' ] ], true ],
		];
	}
}
