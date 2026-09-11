<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Features\Playground\Provider\ToolCall;

defined( 'ABSPATH' ) || exit;

/**
 * Holds a paused turn between the request that stopped and the one that
 * resumes it. Server-side on purpose: what the model asked to run is read
 * back from here, never from the browser that shows it.
 */
final readonly class PendingConfirmationStore {

	private const TTL_SECONDS = 15 * MINUTE_IN_SECONDS;

	/**
	 * @param list<ToolCall> $calls
	 * @param list<string>   $gated_ids
	 */
	public function park( int $user_id, array $calls, array $gated_ids ): PendingConfirmation {
		$pending = new PendingConfirmation( bin2hex( random_bytes( 16 ) ), $calls, $gated_ids );

		set_transient( self::transient_name( $user_id, $pending->key ), $pending->to_array(), self::TTL_SECONDS );

		return $pending;
	}

	/** Single use: a decision cannot be replayed to run the same calls twice. */
	public function take( int $user_id, string $key ): ?PendingConfirmation {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
			return null;
		}

		$name = self::transient_name( $user_id, $key );
		$data = get_transient( $name );
		delete_transient( $name );

		return PendingConfirmation::from_array( $data );
	}

	private static function transient_name( int $user_id, string $key ): string {
		return 'counterhand_chat_pending_' . $user_id . '_' . $key;
	}
}
