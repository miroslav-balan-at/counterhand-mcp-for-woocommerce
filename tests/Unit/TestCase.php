<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;

abstract class TestCase extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Translation wrappers are everywhere in this codebase and always
		// return their first argument in a unit-test context.
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( '_x' )->returnArg( 1 );
		// Stubbed once one test defines it, it stays defined for the whole run, so every
		// test gets a logger rather than only those that happen to run after it.
		Functions\when( 'wc_get_logger' )->justReturn(
			new class() {
				public function error( string $message, array $context = [] ): void {}

				public function warning( string $message, array $context = [] ): void {}
			}
		);
		// Exception messages are escaped for the directory's sniff; tests compare the raw text.
		Functions\when( 'esc_html' )->returnArg( 1 );

		Functions\when( 'sanitize_key' )->alias(
			static fn ( $value ): string => strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) )
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
