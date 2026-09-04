<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Playground;

use Counterhand\Features\Playground\CoreConnector;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The chooser only lists core's connectors and links to core's own screen, so
 * what is pinned here is which connectors are offered — and that the plugin
 * never touches the connector's key option to find out.
 */
final class CoreConnectorTest extends TestCase {

	/** @param array<string, mixed> $connectors */
	private function given_connectors( array $connectors ): void {
		Functions\when( 'wp_get_connectors' )->justReturn( $connectors );
		Functions\when( 'admin_url' )->alias( static fn ( string $path ): string => 'https://store.test/wp-admin/' . $path );
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
		self::assertSame( 'Anthropic', $connectors[0]->name );
		self::assertSame( 'https://platform.claude.com/settings/keys', $connectors[0]->credentials_url );
		self::assertFalse( $connectors[0]->is_connected );
	}

	public function test_never_reads_the_connector_key_option(): void {
		Functions\expect( 'get_option' )->never();
		$this->given_connectors( [ 'anthropic' => $this->anthropic() ] );

		self::assertCount( 1, CoreConnector::ai_providers() );
	}

	public function test_inactive_provider_plugins_are_skipped(): void {
		$this->given_connectors(
			[ 'anthropic' => $this->anthropic( [ 'plugin' => [ 'is_active' => static fn (): bool => false ] ] ) ]
		);

		self::assertSame( [], CoreConnector::ai_providers() );
	}

	public function test_connectors_without_api_key_authentication_are_skipped(): void {
		$this->given_connectors(
			[ 'local' => $this->anthropic( [ 'authentication' => [ 'method' => 'none' ] ] ) ]
		);

		self::assertSame( [], CoreConnector::ai_providers() );
	}

	public function test_non_ai_connectors_are_skipped(): void {
		$this->given_connectors( [ 'spam' => $this->anthropic( [ 'type' => 'spam_filtering' ] ) ] );

		self::assertSame( [], CoreConnector::ai_providers() );
	}

	public function test_no_connectors_when_core_registers_none(): void {
		$this->given_connectors( [] );

		self::assertSame( [], CoreConnector::ai_providers() );
	}

	public function test_settings_url_is_core_connectors_screen(): void {
		$this->given_connectors( [] );

		self::assertSame( 'https://store.test/wp-admin/options-connectors.php', CoreConnector::settings_url() );
	}
}
