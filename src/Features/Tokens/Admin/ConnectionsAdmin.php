<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Admin;

use Counterhand\Features\Settings\SettingsTabInterface;
use Counterhand\Features\Tokens\Domain\TokenRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Read + revoke view of OAuth-granted connections.
 *
 * Connections are born from the OAuth consent flow (never typed here), so
 * there is no create form and no display-once secret — only listing and
 * revocation.
 */
final readonly class ConnectionsAdmin implements SettingsTabInterface {

	private const NONCE_REVOKE = 'counterhand_revoke_connection';

	public function __construct( private TokenRepositoryInterface $repository ) {}

	public function register(): void {
		add_action( 'admin_post_counterhand_revoke_connection', [ $this, 'handle_revoke' ] );
	}

	public function render_tab(): void {
		$list_table = new ConnectionsListTable( $this->repository );
		$list_table->prepare_items();

		include __DIR__ . '/views/tab-connections.php';
	}

	public function handle_revoke(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage connections.', 'counterhand-mcp-for-woocommerce' ) );
		}

		check_admin_referer( self::NONCE_REVOKE );

		$token_row_id = (int) ( $_POST['counterhand_token_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- nonce verified above; (int) cast sanitizes.
		if ( $token_row_id > 0 ) {
			$this->repository->revoke( $token_row_id );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'                => 'counterhand-mcp-connect',
					'view'                => 'connections',
					'counterhand_revoked' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
