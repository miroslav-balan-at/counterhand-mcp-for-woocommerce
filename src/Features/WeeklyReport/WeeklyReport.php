<?php

declare( strict_types=1 );

namespace Counterhand\Features\WeeklyReport;

use Counterhand\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

final readonly class WeeklyReport {

	public const HOOK = 'counterhand_weekly_report';

	/** Who may ask at all; whether the figures can be read is the report routes' own check. */
	public const CAPABILITY = 'manage_woocommerce';

	/** Monday morning in the store's time zone, when last week is complete. */
	private const SEND_AT = 'next monday 07:00';

	public function __construct(
		private FigureReader $reader,
		private ReportSummary $summary,
		private Subscriptions $subscriptions,
	) {}

	public function schedule(): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( ( new \DateTimeImmutable( self::SEND_AT, wp_timezone() ) )->getTimestamp(), self::HOOK );
		}
	}

	/** One event at a time, set again after each run, so the hour stays put across daylight saving. */
	public function send_scheduled(): void {
		foreach ( $this->subscriptions->subscribers() as $user_id ) {
			$this->send_to( $user_id );
		}

		$this->schedule();
	}

	/**
	 * Runs as the person it is for, so their capability and the store's switches decide
	 * what is read, then hands the request back.
	 */
	public function send_to( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( false === $user || ! user_can( $user, self::CAPABILITY ) ) {
			return false;
		}

		$previous = get_current_user_id();
		wp_set_current_user( $user_id );
		$switched = switch_to_user_locale( $user_id );

		try {
			return $this->compose_and_send( $user_id, (string) $user->user_email, get_user_locale( $user_id ) );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
			wp_set_current_user( $previous );
		}
	}

	private function compose_and_send( int $user_id, string $email_address, string $locale ): bool {
		if ( ! $this->reader->can_read( $user_id ) ) {
			return false;
		}

		try {
			$figures = $this->reader->read( $user_id, ReportWeek::last_full_week( new \DateTimeImmutable( 'now', wp_timezone() ) ) );
		} catch ( ToolCallException $exception ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( 'Weekly report not sent: ' . $exception->getMessage(), [ 'source' => 'counterhand-mcp' ] );
			}

			return false;
		}

		$email = ReportEmail::of( $figures, $this->summary->write( $figures, $locale ) );

		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		$mailer = WC()->mailer();

		return (bool) $mailer->send( $email_address, $email->subject, $mailer->wrap_message( $email->heading, $email->body ) );
	}

	public function can_read( int $user_id ): bool {
		return $this->reader->can_read( $user_id );
	}

	public function subscriptions(): Subscriptions {
		return $this->subscriptions;
	}
}
