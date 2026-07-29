<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\Settings;

use AgentGateMcp\Features\Settings\AdminScreen;
use AgentGateMcp\Tests\Unit\TestCase;

final class AdminScreenTest extends TestCase {

	/**
	 * The chat keeps the original slug so bookmarks, the WooCommerce entry and
	 * the OAuth flow pages all keep working after the move off tabs.
	 */
	public function test_slugs_are_stable(): void {
		self::assertSame( 'agentgate-mcp', AdminScreen::Chat->value );
		self::assertSame(
			[ 'agentgate-mcp', 'agentgate-mcp-connect', 'agentgate-mcp-settings', 'agentgate-mcp-log' ],
			array_column( AdminScreen::cases(), 'value' )
		);
	}

	public function test_only_the_chat_is_full_bleed(): void {
		foreach ( AdminScreen::cases() as $screen ) {
			self::assertSame( AdminScreen::Chat === $screen, $screen->is_full_bleed() );
		}
	}

	public function test_every_screen_is_labelled(): void {
		foreach ( AdminScreen::cases() as $screen ) {
			self::assertNotSame( '', $screen->menu_title() );
			self::assertNotSame( '', $screen->page_title() );
			self::assertNotSame( '', $screen->subtitle() );
		}
	}

	public function test_unknown_pages_are_not_ours(): void {
		self::assertNull( AdminScreen::tryFrom( 'woocommerce' ) );
		self::assertNull( AdminScreen::tryFrom( '' ) );
	}
}
