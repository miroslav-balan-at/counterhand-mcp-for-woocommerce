<?php

declare( strict_types=1 );

namespace Counterhand\Features\ActionLog\Persistence;

use Counterhand\Features\ActionLog\ActivityReportInterface;
use Counterhand\Features\ActionLog\ActivitySummary;
use Counterhand\Features\ActionLog\LoggedFailure;

defined( 'ABSPATH' ) || exit;

final readonly class WpdbActivityReport implements ActivityReportInterface {

	private const FAILED = 'error';

	public function since( \DateTimeImmutable $start, int $failures_to_list ): ActivitySummary {
		global $wpdb;

		$table_name = LogSchema::table_name();
		$from       = $start->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

		$counts = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.
			$wpdb->prepare(
				"SELECT COUNT(*) AS calls, COALESCE(SUM(outcome = %s), 0) AS failures FROM {$table_name} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-owned.
				self::FAILED,
				$from
			),
			ARRAY_A
		);

		$failures = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin-owned table, no core API covers it.
			$wpdb->prepare(
				"SELECT tool_name, token_label, created_at FROM {$table_name} WHERE outcome = %s AND created_at >= %s ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-owned.
				self::FAILED,
				$from,
				$failures_to_list
			),
			ARRAY_A
		);

		return new ActivitySummary(
			(int) ( $counts['calls'] ?? 0 ),
			(int) ( $counts['failures'] ?? 0 ),
			array_map(
				static fn ( array $row ): LoggedFailure => new LoggedFailure(
					(string) $row['tool_name'],
					(string) $row['token_label'],
					new \DateTimeImmutable( (string) $row['created_at'], new \DateTimeZone( 'UTC' ) )
				),
				is_array( $failures ) ? $failures : []
			)
		);
	}
}
