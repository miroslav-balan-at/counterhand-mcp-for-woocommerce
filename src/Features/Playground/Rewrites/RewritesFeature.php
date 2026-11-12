<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Rewrites;

use Counterhand\Features\McpServer\ToolDispatcherInterface;
use Counterhand\Features\Playground\Rewrites\Persistence\RewriteSchema;
use Counterhand\Features\Settings\AdminScreen;
use Counterhand\Features\Settings\ScreenBodyInterface;
use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

final readonly class RewritesFeature implements FeatureInterface, ScreenBodyInterface {

	public const START_ACTION  = 'counterhand_rewrites_start';
	public const REVIEW_ACTION = 'counterhand_rewrites_review';

	private const NOTICE     = 'counterhand_rewrites_notice_';
	private const CAPABILITY = 'manage_woocommerce';

	public function __construct( private RewriteJobs $jobs, private RewritePrivacy $privacy ) {}

	public function register(): void {
		add_action( 'admin_init', [ RewriteSchema::class, 'maybe_upgrade' ] );
		$this->privacy->register();
		add_action( RewriteJobs::HOOK, [ $this->jobs, 'run' ] );
		add_action( 'action_scheduler_failed_execution', [ $this->jobs, 'fail_action' ] );
		add_action( 'action_scheduler_unexpected_shutdown', [ $this->jobs, 'fail_action' ] );
		add_action( 'action_scheduler_failed_action', [ $this->jobs, 'fail_action' ] );
		add_action( 'admin_post_' . self::START_ACTION, [ $this, 'handle_start' ] );
		add_action( 'admin_post_' . self::REVIEW_ACTION, [ $this, 'handle_review' ] );
		add_filter( ToolDispatcherInterface::SERVES_TOOLS_FILTER, [ $this, 'serves_tools_here' ] );
		add_action( RewriteJobs::PURGE_HOOK, [ $this->jobs, 'purge' ] );

		if ( ! wp_next_scheduled( RewriteJobs::PURGE_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', RewriteJobs::PURGE_HOOK );
		}
	}

	/**
	 * Applying and undoing write products, so they answer as a tool request does. The
	 * queue only reads, and answering for the whole cron request would change how
	 * other plugins' work in it is saved.
	 */
	public function serves_tools_here( mixed $serves ): bool {
		$action = $_REQUEST['action'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification -- compared against a fixed name; the handler verifies the nonce.

		return true === $serves || ( is_admin() && self::REVIEW_ACTION === $action );
	}

	public function render_body(): void {
		$user_id    = get_current_user_id();
		$blocked    = $this->jobs->blocked_reason( $user_id );
		$keeps_undo = $this->jobs->keeps_undo( $user_id );
		$can_apply  = $this->jobs->writes_products( $user_id );
		$rows       = $this->jobs->rows( $user_id );
		$notice     = get_transient( self::NOTICE . $user_id );
		$fields     = RewriteField::cases();
		$limit      = RewriteJobs::MAX_PRODUCTS;

		if ( is_string( $notice ) ) {
			delete_transient( self::NOTICE . $user_id );
		}

		require __DIR__ . '/views/rewrites.php';
	}

	public function handle_start(): void {
		$this->guard( self::START_ACTION );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
		$category    = absint( $_POST['counterhand_rewrite_category'] ?? 0 );
		$field       = RewriteField::tryFrom( sanitize_key( wp_unslash( $_POST['counterhand_rewrite_field'] ?? '' ) ) ) ?? RewriteField::Description;
		$instruction = sanitize_textarea_field( wp_unslash( $_POST['counterhand_rewrite_instruction'] ?? '' ) );
		// phpcs:enable

		try {
			$queued = $this->jobs->start( get_current_user_id(), $category, $field, $instruction );
			$this->finish(
				sprintf(
					/* translators: %d: number of products. */
					_n( '%d product is queued. Its new text appears below when the model has written it.', '%d products are queued. Their new texts appear below as the model writes them.', $queued, 'counterhand-mcp-for-woocommerce' ),
					$queued
				)
			);
		} catch ( RewriteRefused $refused ) {
			$this->finish( $refused->getMessage() );
		}
	}

	public function handle_review(): void {
		$this->guard( self::REVIEW_ACTION );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
		$decision = sanitize_key( wp_unslash( $_POST['counterhand_rewrite_decision'] ?? '' ) );
		$selected = array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['counterhand_rewrite_items'] ?? [] ) ) ) );
		// phpcs:enable

		if ( [] === $selected ) {
			$this->finish( __( 'Tick the products first.', 'counterhand-mcp-for-woocommerce' ) );
		}

		$user_id = get_current_user_id();

		try {
			match ( $decision ) {
				'apply'   => $this->jobs->apply( $user_id, $selected ),
				'undo'    => $this->jobs->undo( $user_id, $selected ),
				'discard' => $this->jobs->discard( $user_id, $selected ),
				default   => null,
			};
		} catch ( RewriteRefused $refused ) {
			$this->finish( $refused->getMessage() );
		}

		$this->finish( __( 'Done. Each product’s status is below.', 'counterhand-mcp-for-woocommerce' ) );
	}

	private function guard( string $action ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to rewrite products.', 'counterhand-mcp-for-woocommerce' ), 403 );
		}

		check_admin_referer( $action );
	}

	/** Post-redirect-get, so a refresh cannot start a job or apply twice. */
	private function finish( string $message ): never {
		set_transient( self::NOTICE . get_current_user_id(), $message, MINUTE_IN_SECONDS );
		wp_safe_redirect( AdminScreen::Rewrites->url() );
		exit;
	}
}
