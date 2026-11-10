<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

defined( 'ABSPATH' ) || exit;

/** Questions a person asks often, kept for that person only and sent exactly as they typed them. */
final readonly class SavedPrompts {

	public const MAX_PROMPTS = 10;
	public const MAX_LENGTH  = 500;

	private const META_KEY = 'counterhand_saved_prompts';

	/** @return list<string> */
	public function for_user( int $user_id ): array {
		$stored = get_user_meta( $user_id, self::META_KEY, true );

		return array_values( array_filter( is_array( $stored ) ? $stored : [], 'is_string' ) );
	}

	/** @return list<string> */
	public function add( int $user_id, string $prompt ): array {
		$prompt = mb_substr( trim( sanitize_textarea_field( $prompt ) ), 0, self::MAX_LENGTH );
		$saved  = $this->for_user( $user_id );

		if ( '' === $prompt || in_array( $prompt, $saved, true ) ) {
			return $saved;
		}

		return $this->store( $user_id, array_slice( [ $prompt, ...$saved ], 0, self::MAX_PROMPTS ) );
	}

	/** @return list<string> */
	public function remove( int $user_id, string $prompt ): array {
		return $this->store( $user_id, array_values( array_diff( $this->for_user( $user_id ), [ $prompt ] ) ) );
	}

	/**
	 * @param  list<string> $prompts
	 * @return list<string>
	 */
	private function store( int $user_id, array $prompts ): array {
		update_user_meta( $user_id, self::META_KEY, $prompts );

		return $prompts;
	}
}
