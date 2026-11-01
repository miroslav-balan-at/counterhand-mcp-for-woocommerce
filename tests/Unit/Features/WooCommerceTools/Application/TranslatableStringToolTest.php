<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Application;

use Brain\Monkey\Functions;
use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\WooCommerceTools\Application\TranslatableStringTool;
use Counterhand\Features\WooCommerceTools\Descriptors\WpmlCopy;
use Counterhand\Features\WooCommerceTools\Domain\TranslatableString;
use Counterhand\Features\WooCommerceTools\Domain\TranslatableStringPage;
use Counterhand\Features\WooCommerceTools\Infrastructure\TranslatableStringsInterface;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Tests\Unit\TestCase;

final class TranslatableStringToolTest extends TestCase {

	private function strings( bool $can_manage = true, ?TranslatableString $found = null ): TranslatableStringsInterface {
		return new class( $can_manage, $found ) implements TranslatableStringsInterface {

			/** @var list<array{int, string, string}> */
			public array $saved = [];

			public function __construct( private bool $can_manage, private ?TranslatableString $found ) {}

			public function is_installed(): bool {
				return true;
			}

			public function user_can_manage(): bool {
				return $this->can_manage;
			}

			public function page( string $language, string $search, bool $only_missing, int $per_page, int $page ): TranslatableStringPage {
				return new TranslatableStringPage( [], 0 );
			}

			public function find( int $id, string $language ): ?TranslatableString {
				return $this->found;
			}

			public function is_active_language( string $language ): bool {
				return in_array( $language, [ 'de', 'en' ], true );
			}

			public function save( int $id, string $language, string $value ): bool {
				$this->saved[] = [ $id, $language, $value ];

				return true;
			}
		};
	}

	/** @return array{0: TranslatableStringTool, 1: TranslatableStringTool} */
	private function tools( TranslatableStringsInterface $strings ): array {
		Functions\when( '__' )->returnArg();

		return TranslatableStringTool::both( $strings, IntegrationGroup::of( 'wpml', copy: new WpmlCopy() ) );
	}

	public function test_only_setting_needs_confirmation(): void {
		[ $list, $set ] = $this->tools( $this->strings() );

		$this->assertTrue( $list->is_read_only() );
		$this->assertFalse( $list->requires_confirmation() );
		$this->assertTrue( $set->requires_confirmation() );
	}

	/** There is no REST permission callback behind this. */
	public function test_a_user_who_cannot_manage_translations_gets_nothing(): void {
		[ $list ] = $this->tools( $this->strings( can_manage: false ) );

		$this->assertFalse( $list->is_available() );
		$this->expectException( ToolCallException::class );
		$list->execute( [ 'language' => 'de' ] );
	}

	public function test_a_string_out_of_reach_is_not_set(): void {
		$strings   = $this->strings();
		[ , $set ] = $this->tools( $strings );

		try {
			$set->execute(
				[
					'id'       => 5,
					'language' => 'de',
					'value'    => 'AT99',
				]
			);
			$this->fail( 'expected a refusal' );
		} catch ( ToolCallException ) {
			$this->assertSame( [], $strings->saved );
		}
	}

	public function test_a_string_is_saved_without_script(): void {
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $text ): string => (string) preg_replace( '#<script.*?</script>#', '', $text ) );
		$strings   = $this->strings( found: new TranslatableString( 5, 'woocommerce', 'a', 'Title', 'en', null ) );
		[ , $set ] = $this->tools( $strings );

		$set->execute(
			[
				'id'       => 5,
				'language' => 'de',
				'value'    => 'Titel<script>x</script>',
			]
		);

		$this->assertSame( [ [ 5, 'de', 'Titel' ] ], $strings->saved );
	}

	public function test_the_source_language_and_an_inactive_one_are_refused(): void {
		$strings   = $this->strings( found: new TranslatableString( 5, 'woocommerce', 'a', 'Title', 'en', null ) );
		[ , $set ] = $this->tools( $strings );

		foreach ( [ 'en', 'fr' ] as $language ) {
			try {
				$set->execute(
					[
						'id'       => 5,
						'language' => $language,
						'value'    => 'x',
					]
				);
				$this->fail( $language );
			} catch ( ToolCallException ) {
				$this->assertSame( [], $strings->saved );
			}
		}
	}
}
