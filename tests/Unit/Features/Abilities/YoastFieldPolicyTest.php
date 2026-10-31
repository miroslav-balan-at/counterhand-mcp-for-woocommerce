<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Abilities;

use Brain\Monkey\Functions;
use Counterhand\Features\Abilities\YoastFieldPolicy;
use Counterhand\Tests\Unit\TestCase;

final class YoastFieldPolicyTest extends TestCase {

	/**
	 * @dataProvider arguments
	 * @param array<string, mixed> $arguments
	 */
	public function test_verdict( array $arguments, bool $allowed ): void {
		Functions\when( 'home_url' )->justReturn( 'https://store.example' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$this->assertSame( $allowed, ( new YoastFieldPolicy() )->verdict( $arguments )->allowed );
	}

	/** @return iterable<string, array{array<string, mixed>, bool}> */
	public static function arguments(): iterable {
		yield 'plain text' => [ [ 'seo_title' => 'Blue mug' ], true ];
		yield 'a harmless variable' => [ [ 'seo_title' => '%%title%% %%sep%% %%sitename%%' ], true ];
		yield 'a custom field' => [ [ 'seo_title' => '%%cf__price%%' ], false ];
		yield 'a term' => [ [ 'meta_description' => 'x %%ct_product_cat%%' ], false ];
		yield 'a term description' => [ [ 'meta_description' => '%%ct_desc_product_cat%%' ], false ];
		yield 'capitals' => [ [ 'seo_title' => '%%CF__price%%' ], false ];
		yield 'a nested value' => [ [ 'social' => [ 'title' => '%%cf_x%%' ] ], false ];
		yield 'a tag Yoast strips on save' => [ [ 'seo_title' => '%%<i>cf__price%%' ], false ];
		yield 'an octet Yoast strips on save' => [ [ 'seo_title' => '%%c%aaf__price%%' ], false ];
		yield 'octets nested to survive one pass' => [ [ 'seo_title' => '%%c%a%aaaf__price%%' ], false ];
		yield 'an octet removed only because another matched first' => [ [ 'seo_title' => 'x%ab %%%abcf__price%%' ], false ];
		yield 'an octet in plain text' => [ [ 'seo_title' => '100%ab off' ], true ];
		yield 'a canonical on this site' => [ [ 'canonical' => 'https://store.example/mug' ], true ];
		yield 'a canonical on another' => [ [ 'canonical' => 'https://elsewhere.example/mug' ], false ];
		yield 'clearing a canonical' => [ [ 'canonical' => null ], true ];
		yield 'a canonical as a path' => [ [ 'canonical' => '/product/mug' ], true ];
		yield 'the home page as a path' => [ [ 'canonical' => '/' ], true ];
		yield 'a canonical with www' => [ [ 'canonical' => 'https://www.store.example/mug' ], true ];
		yield 'a canonical in capitals' => [ [ 'canonical' => 'https://STORE.example/mug' ], true ];
		yield 'a canonical with no scheme' => [ [ 'canonical' => '//elsewhere.example/mug' ], false ];
		yield 'a canonical a browser reads as another host' => [ [ 'canonical' => '/\\elsewhere.example/mug' ], false ];
		yield 'a bare host, which Yoast would store as a link' => [ [ 'canonical' => 'elsewhere.example/mug' ], false ];
		yield 'a host that only starts like this one' => [ [ 'canonical' => 'https://store.example.elsewhere.example/mug' ], false ];
	}
}
