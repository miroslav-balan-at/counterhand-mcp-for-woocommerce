<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Abilities;

use Brain\Monkey\Functions;
use Counterhand\Features\Abilities\RankMathFieldPolicy;
use Counterhand\Tests\Unit\TestCase;

final class RankMathFieldPolicyTest extends TestCase {

	/**
	 * @dataProvider arguments
	 * @param array<string, mixed> $arguments
	 */
	public function test_verdict( array $arguments, bool $allowed ): void {
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( string $value ): string {
				$filtered = strip_tags( $value );

				while ( preg_match( '/%[a-f0-9]{2}/i', $filtered, $match ) ) {
					$filtered = str_replace( $match[0], '', $filtered );
				}

				return $filtered;
			}
		);

		$this->assertSame( $allowed, ( new RankMathFieldPolicy() )->verdict( $arguments )->allowed );
	}

	/** @return iterable<string, array{array<string, mixed>, bool}> */
	public static function arguments(): iterable {
		yield 'plain text' => [ [ 'title' => 'Blue mug' ], true ];
		yield 'plain variables' => [ [ 'title' => '%title% %sep% %sitename%' ], true ];
		yield 'a date format' => [ [ 'title' => '%date(F Y)% %modified(Y)% %currenttime(H)%' ], true ];
		yield 'a term list' => [ [ 'title' => '%categories(limit=1)% %tags(limit=1)%' ], true ];
		yield 'a custom field' => [ [ 'title' => '%customfield(_price)%' ], false ];
		yield 'a term' => [ [ 'title' => '%customterm(pa_secret)%' ], false ];
		yield 'a term description' => [ [ 'title' => '%customterm_desc(pa_secret)%' ], false ];
		yield 'one nobody has read yet' => [ [ 'title' => '%new_variable(x)%' ], false ];
		yield 'capitals' => [ [ 'title' => '%CUSTOMFIELD(_price)%' ], false ];
		yield 'nested, as set-post-type-seo-settings takes it' => [ [ 'post_types' => [ 'product' => [ 'title' => '%customfield(_price)%' ] ] ], false ];
		yield 'an octet stripped on save' => [ [ 'title' => '%custom%aafield(_price)%' ], false ];
		yield 'a tag stripped when rendering' => [ [ 'title' => '%custom<b>field(_price)%' ], false ];
		yield 'not a string' => [ [ 'enabled' => true ], true ];
	}
}
