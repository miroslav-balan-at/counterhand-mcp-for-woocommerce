<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Asks for a review on wordpress.org, where ratings decide how high a plugin ranks in
 * search. It waits until an app is connected, so it asks someone who has used the
 * plugin, and either button retires it for that person for good. Nothing is rewarded.
 */
final readonly class ReviewInvitation implements FeatureInterface, OverviewCardInterface {

	public const ACTION = 'counterhand_review_invitation';

	public const DISMISSED = 'counterhand_review_invitation';

	private const CAPABILITY = 'manage_woocommerce';

	private const REVIEW_URL = 'https://wordpress.org/support/plugin/counterhand-mcp-for-woocommerce/reviews/#new-post';

	public function __construct( private ConnectionMatcher $connections ) {}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
	}

	public function render_card(): void {
		if ( ! current_user_can( self::CAPABILITY ) || $this->is_retired() || 0 === $this->connections->live_count() ) {
			return;
		}

		require __DIR__ . '/views/review-invitation.php';
	}

	public function handle(): never {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this store.', 'counterhand-mcp-for-woocommerce' ), 403 );
		}

		check_admin_referer( self::ACTION );
		update_user_meta( get_current_user_id(), self::DISMISSED, gmdate( 'Y-m-d' ) );

		if ( 'write' === sanitize_key( wp_unslash( $_POST['counterhand_review'] ?? '' ) ) ) {
			add_filter( 'allowed_redirect_hosts', static fn ( array $hosts ): array => [ ...$hosts, 'wordpress.org' ] );
			wp_safe_redirect( self::REVIEW_URL );
			exit;
		}

		wp_safe_redirect( AdminScreen::Overview->url() );
		exit;
	}

	private function is_retired(): bool {
		return '' !== (string) get_user_meta( get_current_user_id(), self::DISMISSED, true );
	}
}
