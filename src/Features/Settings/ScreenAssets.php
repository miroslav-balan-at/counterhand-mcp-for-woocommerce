<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Shared\StoreMark;

defined( 'ABSPATH' ) || exit;

/**
 * Per-screen styles and scripts for the plugin's admin pages.
 *
 * A service of its own because the loading rules are a workflow of their own:
 * design tokens first, shared admin chrome on every plugin screen, then only
 * what the screen in front of the user actually runs.
 */
final readonly class ScreenAssets {

	public function enqueue(): void {
		$screen = $this->current_screen();

		if ( null === $screen ) {
			return;
		}

		$base_url  = plugins_url( '', COUNTERHAND_PLUGIN_FILE );
		$base_path = COUNTERHAND_PLUGIN_DIR;

		// Design tokens load first; every other sheet reads its custom properties.
		wp_enqueue_style(
			'counterhand-tokens',
			$base_url . '/assets/shared/tokens.css',
			// New in WordPress 7.1, and a missing dependency stops a sheet printing at all.
			wp_style_is( 'wp-theme', 'registered' ) ? [ 'wp-theme' ] : [],
			(string) filemtime( $base_path . '/assets/shared/tokens.css' )
		);

		wp_enqueue_style(
			'counterhand-admin',
			$base_url . '/assets/admin/settings.css',
			[ 'counterhand-tokens' ],
			(string) filemtime( $base_path . '/assets/admin/settings.css' )
		);

		wp_enqueue_script(
			'counterhand-admin',
			$base_url . '/assets/admin/tokens.js',
			[],
			(string) filemtime( $base_path . '/assets/admin/tokens.js' ),
			true
		);

		if ( AdminScreen::Connect === $screen ) {
			if ( ConnectTab::Apps === ConnectTab::current() ) {
				$this->enqueue_connect( $base_url, $base_path );
			}

			return;
		}

		if ( AdminScreen::Chat === $screen ) {
			$this->enqueue_chat( $base_url, $base_path );
		}
	}

	/** Which of our screens is being rendered, or null when it is not ours. */
	private function current_screen(): ?AdminScreen {
		$page = sanitize_key( $_GET['page'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen routing only.

		return AdminScreen::tryFrom( $page );
	}

	private function enqueue_connect( string $base_url, string $base_path ): void {
		wp_enqueue_script(
			'counterhand-connect',
			$base_url . '/assets/admin/connect.js',
			[ 'counterhand-admin' ],
			(string) filemtime( $base_path . '/assets/admin/connect.js' ),
			true
		);

		wp_localize_script(
			'counterhand-connect',
			'counterhandConnect',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'counterhand_connect' ),
				'i18n'    => [
					'checking'    => __( 'Checking the store…', 'counterhand-mcp-for-woocommerce' ),
					'checkFailed' => __( 'The check could not be run.', 'counterhand-mcp-for-woocommerce' ),
					'waiting'     => __( 'Waiting for the app to connect…', 'counterhand-mcp-for-woocommerce' ),
					'connected'   => __( 'Connected', 'counterhand-mcp-for-woocommerce' ),
				],
			]
		);
	}

	private function enqueue_chat( string $base_url, string $base_path ): void {
		wp_enqueue_style(
			'counterhand-chat',
			$base_url . '/assets/admin/chat.css',
			[ 'counterhand-admin' ],
			(string) filemtime( $base_path . '/assets/admin/chat.css' )
		);

		wp_enqueue_script(
			'counterhand-chat',
			$base_url . '/assets/admin/chat.js',
			[],
			(string) filemtime( $base_path . '/assets/admin/chat.js' ),
			true
		);

		wp_localize_script(
			'counterhand-chat',
			'counterhandChat',
			[
				// The assistant speaks as the store, so it wears the store's
				// mark — the same one the OAuth consent screen shows.
				'avatar' => [
					'url'    => StoreMark::url() ?? '',
					'letter' => StoreMark::letter(),
				],
				'i18n'   => [
					'you'           => __( 'You', 'counterhand-mcp-for-woocommerce' ),
					'assistant'     => __( 'Assistant', 'counterhand-mcp-for-woocommerce' ),
					'thinking'      => __( 'Thinking…', 'counterhand-mcp-for-woocommerce' ),
					'failed'        => __( 'The request failed.', 'counterhand-mcp-for-woocommerce' ),
					'arguments'     => __( 'Arguments', 'counterhand-mcp-for-woocommerce' ),
					'result'        => __( 'Result', 'counterhand-mcp-for-woocommerce' ),
					'toolRan'       => __( 'ran', 'counterhand-mcp-for-woocommerce' ),
					'toolFailed'    => __( 'failed', 'counterhand-mcp-for-woocommerce' ),
					'toolDeclined'  => __( 'declined', 'counterhand-mcp-for-woocommerce' ),
					'approvalTitle' => __( 'Approve this change?', 'counterhand-mcp-for-woocommerce' ),
					'approvalHint'  => __( 'The assistant wants to run this. Nothing happens until you decide.', 'counterhand-mcp-for-woocommerce' ),
					'approve'       => __( 'Approve', 'counterhand-mcp-for-woocommerce' ),
					'decline'       => __( 'Decline', 'counterhand-mcp-for-woocommerce' ),
					'approved'      => __( 'Approved', 'counterhand-mcp-for-woocommerce' ),
					'declined'      => __( 'Declined', 'counterhand-mcp-for-woocommerce' ),
					'awaiting'      => __( 'Waiting for your approval…', 'counterhand-mcp-for-woocommerce' ),
					/* translators: 1: tokens sent to the model, 2: tokens it wrote, both in this conversation. */
					'conversation'  => __( 'This conversation: %1$s tokens in, %2$s out', 'counterhand-mcp-for-woocommerce' ),
					'field'         => __( 'Field', 'counterhand-mcp-for-woocommerce' ),
					'before'        => __( 'Now', 'counterhand-mcp-for-woocommerce' ),
					'after'         => __( 'After', 'counterhand-mcp-for-woocommerce' ),
					'wouldRefuse'   => __( 'The store would refuse this:', 'counterhand-mcp-for-woocommerce' ),
					'empty'         => __( '(empty)', 'counterhand-mcp-for-woocommerce' ),
					'forget'        => __( 'Forget this question', 'counterhand-mcp-for-woocommerce' ),
					'nothingToSave' => __( 'Type a question first, then save it.', 'counterhand-mcp-for-woocommerce' ),
					'installing'    => __( 'Installing…', 'counterhand-mcp-for-woocommerce' ),
					'installed'     => __( 'Installed', 'counterhand-mcp-for-woocommerce' ),
					'installFailed' => __( 'The install failed.', 'counterhand-mcp-for-woocommerce' ),
				],
			]
		);
	}
}
