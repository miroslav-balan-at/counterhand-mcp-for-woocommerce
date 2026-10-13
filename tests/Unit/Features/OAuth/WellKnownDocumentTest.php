<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\OAuth;

use Counterhand\Features\OAuth\WellKnownDocument;
use Counterhand\Tests\Unit\TestCase;

final class WellKnownDocumentTest extends TestCase {

	/**
	 * The path has to resolve on its own: when a plugin sharing the
	 * /.well-known/ namespace wins the rewrite rule, our query var is absent
	 * and the path is all we get.
	 *
	 * @dataProvider paths
	 */
	public function test_path_resolves_without_a_query_var( string $path, ?WellKnownDocument $expected ): void {
		self::assertSame( $expected, WellKnownDocument::from_path( $path ) );
	}

	public static function paths(): array {
		return [
			'resource'                => [ '.well-known/oauth-protected-resource', WellKnownDocument::Resource ],
			'resource, trailing slash' => [ '.well-known/oauth-protected-resource/', WellKnownDocument::Resource ],
			'resource, leading slash' => [ '/.well-known/oauth-protected-resource', WellKnownDocument::Resource ],
			'resource, mixed case'    => [ '.well-known/OAuth-Protected-Resource', WellKnownDocument::Resource ],
			'server'                  => [ '.well-known/oauth-authorization-server', WellKnownDocument::Server ],
			'server, trailing slash'  => [ '.well-known/oauth-authorization-server/', WellKnownDocument::Server ],
			'empty request'           => [ '', null ],
			'unrelated page'          => [ 'shop/product', null ],
			'sibling well-known'      => [ '.well-known/ucp', null ],
			'prefix only'             => [ '.well-known', null ],
			'deeper path'             => [ '.well-known/oauth-protected-resource/mcp', null ],
		];
	}

	public function test_query_var_still_resolves_when_our_rule_wins(): void {
		self::assertSame( WellKnownDocument::Resource, WellKnownDocument::try_from_query_var( 'resource' ) );
		self::assertSame( WellKnownDocument::Server, WellKnownDocument::try_from_query_var( 'server' ) );
	}

	public function test_absent_or_unknown_query_var_is_null(): void {
		self::assertNull( WellKnownDocument::try_from_query_var( null ) );
		self::assertNull( WellKnownDocument::try_from_query_var( 'nonsense' ) );
	}
}
