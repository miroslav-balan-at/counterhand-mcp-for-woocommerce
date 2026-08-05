<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Settings;

use Brain\Monkey\Functions;
use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Settings\PublishedScopes;
use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Tests\Unit\TestCase;

/**
 * One authority for what the store offers, read by every stage of the OAuth
 * conversation.
 *
 * The invariant worth pinning: discovery, the consent screen and the grant all
 * answer from here, so a scope for a disabled group cannot be advertised,
 * offered, or minted — before this, all three happened and the tools then
 * (correctly) never appeared, a permission that could be granted but never
 * worked.
 */
final class PublishedScopesTest extends TestCase {

	public function test_a_disabled_group_publishes_neither_axis(): void {
		$published = $this->published(
			[
				'products_read'  => false,
				'products_write' => false,
			]
		);

		$this->assertFalse( $published->includes( ApiScope::ProductsRead ) );
		$this->assertFalse( $published->includes( ApiScope::ProductsWrite ) );
	}

	public function test_the_axes_are_independent(): void {
		$published = $this->published(
			[
				'products_read'  => true,
				'products_write' => false,
			]
		);

		$this->assertTrue(
			$published->includes( ApiScope::ProductsRead ),
			'The read toggle governs the read scope.'
		);
		$this->assertFalse(
			$published->includes( ApiScope::ProductsWrite ),
			'A read toggle must not leak the write scope: that is the axis the consent screen warns about.'
		);
	}

	public function test_a_request_is_partitioned_without_loss(): void {
		$published = $this->published(
			[
				'products_read'  => true,
				'products_write' => false,
			]
		);
		$requested = [ ApiScope::ProductsRead, ApiScope::ProductsWrite ];

		$this->assertSame( [ ApiScope::ProductsRead ], $published->grantable( $requested ) );
		$this->assertSame(
			[ ApiScope::ProductsWrite ],
			$published->withheld( $requested ),
			'Withheld is the remainder, owed to the consent screen as an explanation rather than dropped.'
		);
	}

	public function test_it_mirrors_the_tool_registry_rule(): void {
		// Everything off → nothing published, exactly as tools/list would be empty.
		$all_off = [];
		foreach ( ApiScope::cases() as $scope ) {
			$all_off[ $scope->group()->read_option_key() ]  = false;
			$all_off[ $scope->group()->write_option_key() ] = false;
		}

		$this->assertSame( [], $this->published( $all_off )->values() );
	}

	private function published( array $settings ): PublishedScopes {
		Functions\when( 'get_option' )->justReturn( $settings );

		return new PublishedScopes( new PluginSettings() );
	}
}
