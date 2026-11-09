<?php

declare( strict_types=1 );

namespace Counterhand\Features\WeeklyReport;

defined( 'ABSPATH' ) || exit;

/** A Monday-to-Sunday week in the store's time zone, which is what the sales report counts in. */
final readonly class ReportWeek {

	private function __construct(
		public \DateTimeImmutable $first_day,
		public \DateTimeImmutable $last_day,
	) {}

	public static function last_full_week( \DateTimeImmutable $now ): self {
		$monday = $now->modify( 'monday this week' )->setTime( 0, 0 )->modify( '-7 days' );

		return new self( $monday, $monday->modify( '+6 days' ) );
	}

	public function previous(): self {
		return new self( $this->first_day->modify( '-7 days' ), $this->last_day->modify( '-7 days' ) );
	}

	/** @return array{date_min: string, date_max: string} */
	public function range(): array {
		return [
			'date_min' => $this->first_day->format( 'Y-m-d' ),
			'date_max' => $this->last_day->format( 'Y-m-d' ),
		];
	}

	public function label(): string {
		return sprintf(
			/* translators: 1: first day of the week, 2: last day of the week, both as dates. */
			__( '%1$s to %2$s', 'counterhand-mcp-for-woocommerce' ),
			wp_date( (string) get_option( 'date_format' ), $this->first_day->getTimestamp(), $this->first_day->getTimezone() ),
			wp_date( (string) get_option( 'date_format' ), $this->last_day->getTimestamp(), $this->last_day->getTimezone() )
		);
	}
}
