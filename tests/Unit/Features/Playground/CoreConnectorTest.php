<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\Playground;

use AgentGateMcp\Features\Playground\CoreConnector;
use AgentGateMcp\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The chooser writes the key into core's own setting, so the filtering rules
 * that decide which connectors are offered are pinned here.
 */
final class CoreConnectorTest extends TestCase {

	/** @param array<string, mixed> $connectors */
	private function given_connectors( array $connectors, string $stored_key = '' ): void {
		Functions\when( 'wp_get_connectors' )->justReturn( $connectors );
		Functions\when( 'get_option' )->justReturn( $stored_key );
	}

	/** @param array<string, mixed> $overrides */
	private function anthropic( array $overrides = [] ): array {
		return array_merge(
			[
				'name'           => 'Anthropic',
				'type'           => 'ai_provider',
				'authentication' => [
					'method'          => 'api_key',
					'setting_name'    => 'connectors_ai_anthropic_api_key',
					'credentials_url' => 'https://platform.claude.com/settings/keys',
				],
				'plugin'         => [ 'is_active' => static fn (): bool => true ],
			],
			$overrides
		);
	}

	public function test_reads_core_connector_data(): void {
		$this->given_connectors( [ 'anthropic' => $this->anthropic() ] );

		$connectors = CoreConnector::ai_providers();

		self::assertCount( 1, $connectors );
		self::assertSame( 'anthropic', $connectors[0]->id );
		self::assertSame( 'connectors_ai_anthropic_api_key', $connectors[0]->setting_name );
		self::assertSame( 'https://platform.claude.com/settings/keys', $connectors[0]->credentials_url );
		self::assertFalse( $connectors[0]->has_key );
		self::assertFalse( $connectors[0]->is_connected );
	}

	public function test_a_stored_key_is_detected(): void {
		$this->given_connectors( [ 'anthropic' => $this->anthropic() ], 'sk-ant-stored' );

		self::assertTrue( CoreConnector::ai_providers()[0]->has_key );
	}

	public function test_inactive_provider_plugins_are_skipped(): void {
		$this->given_connectors(
			[ 'anthropic' => $this->anthropic( [ 'plugin' => [ 'is_active' => static fn (): bool => false ] ] ) ]
		);

		self::assertSame( [], CoreConnector::ai_providers() );
	}

	public function test_connectors_without_a_key_setting_are_skipped(): void {
		$this->given_connectors(
			[ 'local' => $this->anthropic( [ 'authentication' => [ 'method' => 'none' ] ] ) ]
		);

		self::assertSame( [], CoreConnector::ai_providers() );
	}

	public function test_non_ai_connectors_are_skipped(): void {
		$this->given_connectors( [ 'spam' => $this->anthropic( [ 'type' => 'spam_filtering' ] ) ] );

		self::assertSame( [], CoreConnector::ai_providers() );
	}

	public function test_find_returns_null_for_an_unknown_id(): void {
		$this->given_connectors( [ 'anthropic' => $this->anthropic() ] );

		self::assertNotNull( CoreConnector::find( 'anthropic' ) );
		self::assertNull( CoreConnector::find( 'not-a-provider' ) );
	}

	public function test_no_connectors_when_core_registers_none(): void {
		$this->given_connectors( [] );

		self::assertSame( [], CoreConnector::ai_providers() );
	}
}
