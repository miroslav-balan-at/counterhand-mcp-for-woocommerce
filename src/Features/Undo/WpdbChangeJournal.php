<?php

declare( strict_types=1 );

namespace Counterhand\Features\Undo;

use Counterhand\Features\McpServer\ChangeJournalInterface;
use Counterhand\Features\McpServer\ChangeKind;
use Counterhand\Features\McpServer\ChangePlan;
use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Undo\Persistence\ChangeSchema;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned table, no core API covers it, and its name is plugin-owned.
final readonly class WpdbChangeJournal implements ChangeJournalInterface, ChangeRepositoryInterface {

	public const RETENTION_DAYS = 30;

	public function record( AuthenticatedAgent $agent, string $tool_name, ChangePlan $plan, array $before, array $after ): ?string {
		global $wpdb;

		// A store updated without anyone visiting the admin would otherwise lose records.
		ChangeSchema::maybe_upgrade();

		$key = bin2hex( random_bytes( 16 ) );

		$saved = $wpdb->insert(
			ChangeSchema::table_name(),
			[
				'change_key'   => $key,
				'token_id'     => $agent->token->id,
				'user_id'      => $agent->token->owner_user_id,
				'tool_name'    => $tool_name,
				'kind'         => $plan->kind->value,
				'path'         => (string) wp_json_encode( $plan->path ),
				'fields'       => (string) wp_json_encode( $plan->fields ),
				'before_state' => (string) wp_json_encode( $before ),
				'after_state'  => (string) wp_json_encode( $after ),
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
			]
		);

		return false === $saved ? null : $key;
	}

	public function find( string $key, AuthenticatedAgent $agent ): ?RecordedChange {
		global $wpdb;

		$table = ChangeSchema::table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE change_key = %s AND token_id = %d AND user_id = %d AND created_at >= %s",
				$key,
				$agent->token->id,
				$agent->token->owner_user_id,
				self::oldest()
			)
		);

		return is_object( $row ) ? self::from_row( $row ) : null;
	}

	public function recent( AuthenticatedAgent $agent, int $limit ): array {
		global $wpdb;

		$table = ChangeSchema::table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE token_id = %d AND user_id = %d AND created_at >= %s ORDER BY id DESC LIMIT %d",
				$agent->token->id,
				$agent->token->owner_user_id,
				self::oldest(),
				$limit
			)
		);

		return array_map( [ self::class, 'from_row' ], (array) $rows );
	}

	public function claim( string $key ): bool {
		global $wpdb;

		$table = ChangeSchema::table_name();

		return 1 === $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET undone_at = %s WHERE change_key = %s AND undone_at IS NULL",
				gmdate( 'Y-m-d H:i:s' ),
				$key
			)
		);
	}

	public function release( string $key ): void {
		global $wpdb;

		$table = ChangeSchema::table_name();

		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET undone_at = NULL WHERE change_key = %s", $key ) );
	}

	public function purge_expired(): void {
		global $wpdb;

		$table = ChangeSchema::table_name();

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", self::oldest() ) );
	}

	private static function oldest(): string {
		return gmdate( 'Y-m-d H:i:s', time() - self::RETENTION_DAYS * DAY_IN_SECONDS );
	}

	private static function from_row( object $row ): RecordedChange {
		return new RecordedChange(
			(string) $row->change_key,
			(string) $row->tool_name,
			new ChangePlan(
				ChangeKind::from( (string) $row->kind ),
				(array) json_decode( (string) $row->path, true ),
				array_values( array_map( 'strval', (array) json_decode( (string) $row->fields, true ) ) )
			),
			(array) json_decode( (string) $row->before_state, true ),
			(array) json_decode( (string) $row->after_state, true ),
			(string) $row->created_at,
			null === $row->undone_at ? null : (string) $row->undone_at
		);
	}
}
