<?php

declare( strict_types=1 );

namespace Counterhand\Features\WeeklyReport;

defined( 'ABSPATH' ) || exit;

/** Each person asks for the report themselves; nobody is signed up for anyone else. */
final readonly class Subscriptions {

	private const META_KEY = 'counterhand_weekly_report';

	public function is_subscribed( int $user_id ): bool {
		return '1' === get_user_meta( $user_id, self::META_KEY, true );
	}

	public function subscribe( int $user_id ): void {
		update_user_meta( $user_id, self::META_KEY, '1' );
	}

	public function unsubscribe( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
	}

	/** @return list<int> */
	public function subscribers(): array {
		return array_map(
			'intval',
			get_users(
				[
					'meta_key'   => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- once a week, over users.
					'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- once a week, over users.
					'fields'     => 'ID',
				]
			)
		);
	}
}
