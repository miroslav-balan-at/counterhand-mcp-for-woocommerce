<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Playground;

use Counterhand\Features\Licensing\Licence;
use Counterhand\Features\Playground\PlaygroundFeature;
use Counterhand\Tests\Unit\TestCase;

/**
 * The chat is behind the licence, like the endpoint is.
 *
 * It shipped ungated on the reasoning that someone evaluating the plugin should
 * see it work. But it runs every tool an external assistant could, so that gave
 * the product away and left the licence guarding only the harder-to-use half.
 * The trial is the evaluation path now, and nothing here knows about trials:
 * can_use_premium_code() is true throughout one.
 */
final class ChatLicenceGateTest extends TestCase {

	public function test_an_unlicensed_store_gets_no_chat_handlers(): void {
		$this->feature( licensed: false )->register();

		$this->assertFalse(
			has_action( 'wp_ajax_counterhand_chat_send' ),
			'Without a licence the chat must not accept a request: the endpoint being gated is no use if this is not.'
		);
	}

	public function test_a_licensed_store_gets_them(): void {
		$this->feature( licensed: true )->register();

		$this->assertNotFalse( has_action( 'wp_ajax_counterhand_chat_send' ) );
	}

	/*
	 * render_tab() is not asserted on here. It emits markup through
	 * esc_html_e() and friends, which these unit tests do not load, and
	 * stubbing WordPress' escaping layer to read one string back would test the
	 * stub. That it shows the trial invitation rather than a dead chat box is
	 * checked in a browser.
	 */

	/**
	 * Real collaborators rather than doubles: the classes are `final` by
	 * convention and cannot be stubbed, and both gated paths return before
	 * touching any of them, so none is exercised here.
	 */
	private function feature( bool $licensed ): PlaygroundFeature {
		$dispatcher     = new \Counterhand\Features\McpServer\ToolDispatcher(
			new \Counterhand\Features\McpServer\ToolRegistry( new \Counterhand\Features\Settings\PluginSettings() )
		);
		$chat_settings  = new \Counterhand\Features\Playground\ChatSettings();
		$chat_providers = new \Counterhand\Features\Playground\Provider\ProviderRegistry();

		return new PlaygroundFeature(
			$dispatcher,
			new \Counterhand\Features\Playground\AgentLoop( $dispatcher ),
			$chat_settings,
			$chat_providers,
			new \Counterhand\Features\Playground\ModelConnect( $chat_settings, $chat_providers ),
			new \Counterhand\Features\Settings\StoreToolPolicy( new \Counterhand\Features\Settings\PluginSettings() ),
			$this->licence( $licensed )
		);
	}

	private function licence( bool $active ): Licence {
		return new class( $active ) implements Licence {
			public function __construct( private bool $active ) {}

			public function is_active(): bool {
				return $this->active;
			}

			public function upgrade_url(): string {
				return 'https://example.test/upgrade';
			}

			public function account_url(): string {
				return 'https://example.test/account';
			}
		};
	}
}
