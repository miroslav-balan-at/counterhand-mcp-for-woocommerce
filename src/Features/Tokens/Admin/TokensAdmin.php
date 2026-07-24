<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Tokens\Admin;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Features\Tokens\Domain\GrantedScopeSet;
use AgentGateMcp\Features\Tokens\Domain\TokenRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Admin-side token management: create (display-once), list, revoke.
 */
final readonly class TokensAdmin {

	private const NONCE_CREATE = 'agmcp_create_token';
	private const NONCE_REVOKE = 'agmcp_revoke_token';

	public function __construct( private TokenRepositoryInterface $repository ) {}

	public function register(): void {
		add_action( 'admin_post_agmcp_create_token', [ $this, 'handle_create' ] );
		add_action( 'admin_post_agmcp_revoke_token', [ $this, 'handle_revoke' ] );
	}

	public function render_tab(): void {
		$new_token = $this->consume_display_once_token();

		$list_table = new TokensListTable( $this->repository );
		$list_table->prepare_items();

		$scopes       = ApiScope::cases();
		$create_nonce = wp_create_nonce( self::NONCE_CREATE );
		$revoke_nonce = wp_create_nonce( self::NONCE_REVOKE );

		include __DIR__ . '/views/tab-tokens.php';
	}

	public function handle_create(): void {
		$this->guard( self::NONCE_CREATE );

		$label = sanitize_text_field( wp_unslash( $_POST['agmcp_label'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in guard() above.
		if ( '' === $label ) {
			$this->redirect_back( [ 'agmcp_error' => 'label' ] );
		}

		$requested_scopes = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['agmcp_scopes'] ?? [] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in guard() above.

		// Reject unknown scopes outright — never silently persist garbage.
		foreach ( $requested_scopes as $requested_scope ) {
			if ( null === ApiScope::tryFrom( $requested_scope ) ) {
				$this->redirect_back( [ 'agmcp_error' => 'scope' ] );
			}
		}

		$scope_set = GrantedScopeSet::from_values( $requested_scopes );
		if ( $scope_set->is_empty() ) {
			$this->redirect_back( [ 'agmcp_error' => 'scope' ] );
		}

		$expires_at  = null;
		$expiry_days = (int) ( $_POST['agmcp_expiry_days'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- nonce verified in guard(); (int) cast sanitizes.
		if ( in_array( $expiry_days, [ 30, 90, 365 ], true ) ) {
			$expires_at = ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->modify( '+' . $expiry_days . ' days' );
		}

		$plain_token = $this->repository->create( $label, $scope_set, get_current_user_id(), $expires_at );

		// Display-once: park the token in a short-lived transient, deleted on first render.
		$display_key = wp_generate_password( 20, false );
		set_transient( 'agmcp_new_token_' . $display_key, $plain_token->to_string(), 5 * MINUTE_IN_SECONDS );

		$this->redirect_back( [ 'agmcp_new' => $display_key ] );
	}

	public function handle_revoke(): void {
		$this->guard( self::NONCE_REVOKE );

		$token_row_id = (int) ( $_POST['agmcp_token_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- nonce verified in guard(); (int) cast sanitizes.
		if ( $token_row_id > 0 ) {
			$this->repository->revoke( $token_row_id );
		}

		$this->redirect_back( [ 'agmcp_revoked' => '1' ] );
	}

	private function consume_display_once_token(): ?string {
		$display_key = sanitize_text_field( wp_unslash( $_GET['agmcp_new'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only render of a one-time key.
		if ( '' === $display_key ) {
			return null;
		}

		$transient_key = 'agmcp_new_token_' . $display_key;
		$token         = get_transient( $transient_key );
		delete_transient( $transient_key );

		return is_string( $token ) && '' !== $token ? $token : null;
	}

	private function guard( string $nonce_action ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage API tokens.', 'agentgate-mcp-for-woocommerce' ) );
		}

		check_admin_referer( $nonce_action );
	}

	private function redirect_back( array $query_args ): never {
		$url = add_query_arg(
			array_merge(
				[
					'page' => 'agentgate-mcp',
					'tab'  => 'tokens',
				],
				$query_args
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
