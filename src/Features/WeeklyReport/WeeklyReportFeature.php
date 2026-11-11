<?php

declare( strict_types=1 );

namespace Counterhand\Features\WeeklyReport;

use Counterhand\Features\Settings\AdminScreen;
use Counterhand\Features\Settings\DashboardCardInterface;
use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

final readonly class WeeklyReportFeature implements FeatureInterface, DashboardCardInterface {

	public const SUBSCRIBE_ACTION = 'counterhand_weekly_report_subscription';
	public const SEND_ACTION      = 'counterhand_weekly_report_send';

	private const NOTICE = 'counterhand_weekly_report_notice_';

	public function __construct( private WeeklyReport $report ) {}

	public function register(): void {
		add_action( WeeklyReport::HOOK, [ $this->report, 'send_scheduled' ] );
		add_action( 'admin_post_' . self::SUBSCRIBE_ACTION, [ $this, 'handle_subscription' ] );
		add_action( 'admin_post_' . self::SEND_ACTION, [ $this, 'handle_send' ] );
		add_action( 'init', [ $this->report, 'schedule' ] );
	}

	public function render_card(): void {
		$user_id = get_current_user_id();
		if ( ! current_user_can( WeeklyReport::CAPABILITY ) ) {
			return;
		}

		$can_read   = $this->report->can_read( $user_id );
		$subscribed = $this->report->subscriptions()->is_subscribed( $user_id );
		$email      = wp_get_current_user()->user_email;
		$next       = wp_next_scheduled( WeeklyReport::HOOK );
		$notice     = get_transient( self::NOTICE . $user_id );

		if ( is_string( $notice ) ) {
			delete_transient( self::NOTICE . $user_id );
		}

		require __DIR__ . '/views/card.php';
	}

	public function handle_subscription(): void {
		$this->guard( self::SUBSCRIBE_ACTION );

		$user_id = get_current_user_id();
		$wanted  = 'on' === sanitize_key( wp_unslash( $_POST['counterhand_weekly_report'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().

		if ( $wanted ) {
			$this->report->subscriptions()->subscribe( $user_id );
			$this->finish( __( 'You will get the report every Monday morning.', 'counterhand-mcp-for-woocommerce' ) );
		}

		$this->report->subscriptions()->unsubscribe( $user_id );
		$this->finish( __( 'You will not get the weekly report any more.', 'counterhand-mcp-for-woocommerce' ) );
	}

	public function handle_send(): void {
		$this->guard( self::SEND_ACTION );

		$this->finish(
			$this->report->send_to( get_current_user_id() )
				? __( 'Last week’s report is on its way to your email address.', 'counterhand-mcp-for-woocommerce' )
				: __( 'The report could not be sent. Check that Reports can be read in Access, and the WooCommerce logs.', 'counterhand-mcp-for-woocommerce' )
		);
	}

	private function guard( string $action ): void {
		if ( ! current_user_can( WeeklyReport::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to see store reports.', 'counterhand-mcp-for-woocommerce' ), 403 );
		}

		check_admin_referer( $action );
	}

	/** Post-redirect-get, so a refresh cannot send a second report. */
	private function finish( string $message ): never {
		set_transient( self::NOTICE . get_current_user_id(), $message, MINUTE_IN_SECONDS );
		wp_safe_redirect( AdminScreen::Overview->url() );
		exit;
	}
}
