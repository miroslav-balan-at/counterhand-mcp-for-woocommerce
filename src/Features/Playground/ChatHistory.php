<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

defined( 'ABSPATH' ) || exit;

/**
 * The conversation as the browser posts it back. A message is kept or dropped whole and
 * never edited: Claude refuses a history whose thinking blocks no longer match.
 */
final class ChatHistory {

	/** What a provider's messages hold. A posted "system" would speak with the store's authority. */
	private const ROLES = [ 'user', 'assistant', 'tool' ];

	/** @return list<array<string,mixed>> */
	public static function from_posted( string $posted ): array {
		$history = json_decode( $posted, true );

		if ( ! is_array( $history ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$history,
				static fn ( mixed $message ): bool => is_array( $message ) && in_array( $message['role'] ?? null, self::ROLES, true )
			)
		);
	}
}
