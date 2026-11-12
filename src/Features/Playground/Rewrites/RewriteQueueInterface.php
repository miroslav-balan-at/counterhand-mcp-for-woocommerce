<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Rewrites;

use Counterhand\Features\Playground\Provider\TokenUsage;

defined( 'ABSPATH' ) || exit;

/**
 * Every read a person reaches names the user, so one administrator's rewrites are
 * never another's to apply or undo. Only the queue runner reads by id alone.
 */
interface RewriteQueueInterface {

	/**
	 * @param  list<int> $product_ids
	 * @return list<int> The new item ids.
	 */
	public function add( string $job_key, int $user_id, RewriteField $field, string $instruction, array $product_ids ): array;

	public function find( int $id ): ?RewriteItem;

	public function owned( int $id, int $user_id ): ?RewriteItem;

	/** @return list<RewriteItem> */
	public function latest_job( int $user_id ): array;

	/** Moves one of this person's items on only if it is still where it was, so two clicks cannot both act on it. */
	public function claim( int $id, int $user_id, RewriteStatus $from, RewriteStatus $to ): bool;

	public function propose( int $id, string $product_name, string $original, string $proposal, TokenUsage $usage ): void;

	public function fail( int $id, string $message ): void;

	public function apply( int $id, ?string $change_key, string $message ): void;

	public function mark( int $id, RewriteStatus $status, string $message ): void;

	public function forget_older_than( \DateTimeImmutable $cutoff ): void;

	/** @return list<RewriteItem> */
	public function made_by( int $user_id ): array;

	/** @return int How many items went. */
	public function forget_user( int $user_id ): int;
}
