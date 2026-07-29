<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Tokens\Admin;

use AgentGateMcp\Features\Tokens\Domain\TokenRepositoryInterface;
use AgentGateMcp\Features\Tokens\Domain\TokenStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Read + revoke view of OAuth-granted connections.
 *
 * Connections are born from the OAuth consent flow (never typed here), so
 * there is no create form and no display-once secret — only listing and
 * revocation.
 */
final readonly class ConnectionsAdmin {

	private const NONCE_REVOKE = 'agmcp_revoke_connection';

	public function __construct( private TokenRepositoryInterface $repository ) {}

	public function register(): void {
		add_action( 'admin_post_agmcp_revoke_connection', [ $this, 'handle_revoke' ] );
	}

	/** Live connections, for the Connect screen's tab badge. */
	public function active_count(): int {
		$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

		return count(
			array_filter(
				$this->repository->list_all(),
				static fn ( $token ): bool => TokenStatus::Active === $token->status && ! $token->is_expired( $now )
			)
		);
	}

	public function render_tab(): void {
		$list_table = new ConnectionsListTable( $this->repository );
		$list_table->prepare_items();

		include __DIR__ . '/views/tab-connections.php';
	}

	public function handle_revoke(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage connections.', 'agentgate-mcp-for-woocommerce' ) );
		}

		check_admin_referer( self::NONCE_REVOKE );

		$token_row_id = (int) ( $_POST['agmcp_token_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- nonce verified above; (int) cast sanitizes.
		if ( $token_row_id > 0 ) {
			$this->repository->revoke( $token_row_id );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'          => 'agentgate-mcp-connect',
					'view'          => 'connections',
					'agmcp_revoked' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
