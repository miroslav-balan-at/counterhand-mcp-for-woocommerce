<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Infrastructure;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Counterhand\Features\McpServer\ToolDispatcherInterface;
use Counterhand\Features\WooCommerceTools\Infrastructure\MultilingualRestMode;
use Counterhand\Tests\Unit\TestCase;

final class MultilingualRestModeTest extends TestCase {

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_store_without_multilingual_is_left_alone(): void {
		Filters\expectApplied( ToolDispatcherInterface::SERVES_TOOLS_FILTER )->never();

		$this->assertFalse( ( new MultilingualRestMode() )->include_tool_requests( false ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_tool_request_counts_as_rest_but_never_while_woocommerce_authenticates(): void {
		define( 'WCML_VERSION', 'test' );
		$authenticating = false;
		Functions\when( 'doing_filter' )->alias(
			static function ( string $name ) use ( &$authenticating ): bool {
				return $authenticating && 'rest_authentication_errors' === $name;
			}
		);
		Filters\expectApplied( ToolDispatcherInterface::SERVES_TOOLS_FILTER )->andReturn( true );

		$mode = new MultilingualRestMode();
		$this->assertTrue( $mode->include_tool_requests( false ) );

		$authenticating = true;
		$this->assertFalse( $mode->include_tool_requests( false ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_any_other_request_keeps_its_answer(): void {
		define( 'WCML_VERSION', 'test' );
		Functions\when( 'doing_filter' )->justReturn( false );

		$mode = new MultilingualRestMode();
		$this->assertFalse( $mode->include_tool_requests( false ) );
		$this->assertTrue( $mode->include_tool_requests( true ) );
	}
}
