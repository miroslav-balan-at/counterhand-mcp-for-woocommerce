<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Rewrites;

use Counterhand\Features\Playground\Provider\TokenUsage;
use Counterhand\Features\Playground\Rewrites\Persistence\RewriteSchema;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned table; the name comes from RewriteSchema, every value is prepared.
final readonly class WpdbRewriteQueue implements RewriteQueueInterface {

	public function add( string $job_key, int $user_id, RewriteField $field, string $instruction, array $product_ids ): array {
		global $wpdb;

		// A store updated without anyone visiting the admin would otherwise have no table.
		RewriteSchema::maybe_upgrade();

		$ids = [];
		foreach ( $product_ids as $product_id ) {
			$saved = $wpdb->insert(
				RewriteSchema::table_name(),
				[
					'job_key'      => $job_key,
					'user_id'      => $user_id,
					'product_id'   => $product_id,
					'field'        => $field->value,
					'instruction'  => $instruction,
					'status'       => RewriteStatus::Queued->value,
					'product_name' => '',
					'original'     => '',
					'proposal'     => '',
					'message'      => '',
					'created_at'   => gmdate( 'Y-m-d H:i:s' ),
				]
			);

			if ( false !== $saved ) {
				$ids[] = (int) $wpdb->insert_id;
			}
		}

		return $ids;
	}

	public function find( int $id ): ?RewriteItem {
		global $wpdb;

		$table = RewriteSchema::table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		return is_object( $row ) ? self::from_row( $row ) : null;
	}

	public function owned( int $id, int $user_id ): ?RewriteItem {
		global $wpdb;

		$table = RewriteSchema::table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND user_id = %d", $id, $user_id ) );

		return is_object( $row ) ? self::from_row( $row ) : null;
	}

	public function latest_job( int $user_id ): array {
		global $wpdb;

		$table = RewriteSchema::table_name();
		$job   = $wpdb->get_var( $wpdb->prepare( "SELECT job_key FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id ) );

		if ( ! is_string( $job ) ) {
			return [];
		}

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE job_key = %s AND user_id = %d ORDER BY id ASC", $job, $user_id ) );

		return array_map( [ self::class, 'from_row' ], (array) $rows );
	}

	public function claim( int $id, int $user_id, RewriteStatus $from, RewriteStatus $to ): bool {
		global $wpdb;

		$table = RewriteSchema::table_name();

		return 1 === $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = %s WHERE id = %d AND user_id = %d AND status = %s", $to->value, $id, $user_id, $from->value ) );
	}

	public function propose( int $id, string $product_name, string $original, string $proposal, TokenUsage $usage ): void {
		$this->update(
			$id,
			[
				'status'        => RewriteStatus::Proposed->value,
				'product_name'  => $product_name,
				'original'      => $original,
				'proposal'      => $proposal,
				'message'       => '',
				'input_tokens'  => $usage->input,
				'output_tokens' => $usage->output,
			]
		);
	}

	public function fail( int $id, string $message ): void {
		$this->mark( $id, RewriteStatus::Failed, $message );
	}

	public function apply( int $id, ?string $change_key, string $message ): void {
		$this->update(
			$id,
			[
				'status'     => RewriteStatus::Applied->value,
				'change_key' => $change_key,
				'message'    => $message,
			]
		);
	}

	public function mark( int $id, RewriteStatus $status, string $message ): void {
		$this->update(
			$id,
			[
				'status'  => $status->value,
				'message' => $message,
			]
		);
	}

	public function forget_older_than( \DateTimeImmutable $cutoff ): void {
		global $wpdb;

		RewriteSchema::maybe_upgrade();

		$table = RewriteSchema::table_name();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) ) );
	}

	/** @param array<string, mixed> $values */
	private function update( int $id, array $values ): void {
		global $wpdb;

		$wpdb->update( RewriteSchema::table_name(), $values, [ 'id' => $id ] );
	}

	public function made_by( int $user_id ): array {
		global $wpdb;

		RewriteSchema::maybe_upgrade();

		$table = RewriteSchema::table_name();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id ASC", $user_id ) );

		return array_map( [ self::class, 'from_row' ], (array) $rows );
	}

	public function forget_user( int $user_id ): int {
		global $wpdb;

		RewriteSchema::maybe_upgrade();

		$table = RewriteSchema::table_name();

		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE user_id = %d", $user_id ) );
	}

	private static function from_row( object $row ): RewriteItem {
		return new RewriteItem(
			id: (int) $row->id,
			job_key: (string) $row->job_key,
			user_id: (int) $row->user_id,
			product_id: (int) $row->product_id,
			field: RewriteField::tryFrom( (string) $row->field ) ?? RewriteField::Description,
			instruction: (string) $row->instruction,
			status: RewriteStatus::tryFrom( (string) $row->status ) ?? RewriteStatus::Failed,
			product_name: (string) $row->product_name,
			original: (string) $row->original,
			proposal: (string) $row->proposal,
			message: (string) $row->message,
			change_key: null === $row->change_key ? null : (string) $row->change_key,
			usage: new TokenUsage( (int) $row->input_tokens, (int) $row->output_tokens ),
			created_at: new \DateTimeImmutable( (string) $row->created_at, new \DateTimeZone( 'UTC' ) ),
		);
	}
}
