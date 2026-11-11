<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Settings;

use Counterhand\Features\Settings\AdminScreen;
use Counterhand\Tests\Unit\TestCase;

final class AdminScreenTest extends TestCase {

	/**
	 * Screens were renamed and reordered, never re-addressed: bookmarks, links in
	 * weekly report emails and the consent screen keep working.
	 */
	public function test_slugs_are_stable(): void {
		self::assertSame( 'counterhand-mcp', AdminScreen::Chat->value );
		self::assertSame(
			[ 'counterhand-mcp-dashboard', 'counterhand-mcp', 'counterhand-mcp-rewrites', 'counterhand-mcp-connect', 'counterhand-mcp-settings', 'counterhand-mcp-log' ],
			array_column( AdminScreen::cases(), 'value' )
		);
	}

	/** Other plugins became a section of Access; its old address is only redirected now. */
	public function test_other_plugins_is_no_screen_of_its_own(): void {
		self::assertNull( AdminScreen::tryFrom( AdminScreen::OTHER_PLUGINS_SLUG ) );
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
