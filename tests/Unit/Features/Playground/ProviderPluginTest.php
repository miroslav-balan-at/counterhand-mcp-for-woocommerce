<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\Playground;

use AgentGateMcp\Features\Playground\ProviderPlugin;
use AgentGateMcp\Tests\Unit\TestCase;

final class ProviderPluginTest extends TestCase {

	public function test_only_official_wordpress_org_slugs_are_installable(): void {
		self::assertSame(
			[ 'ai-provider-for-anthropic', 'ai-provider-for-openai', 'ai-provider-for-google' ],
			array_column( ProviderPlugin::cases(), 'value' )
		);
	}

	/** @dataProvider hostile_slugs */
	public function test_arbitrary_slugs_never_reach_the_installer( string $slug ): void {
		self::assertNull( ProviderPlugin::tryFrom( $slug ) );
	}

	public static function hostile_slugs(): array {
		return [
			'random plugin'   => [ 'hello-dolly' ],
			'path traversal'  => [ '../../evil' ],
			'lookalike'       => [ 'ai-provider-for-anthropic-pro' ],
			'empty'           => [ '' ],
		];
	}

	public function test_every_provider_has_a_label(): void {
		foreach ( ProviderPlugin::cases() as $plugin ) {
			self::assertNotSame( '', $plugin->label() );
		}
	}
}
