<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

defined( 'ABSPATH' ) || exit;

/**
 * What the administrator decided about a parked turn, call by call.
 * Anything they did not explicitly approve is a no.
 */
final readonly class ApprovalDecisions {

	/** @param array<string, bool> $approved_by_call_id */
	private function __construct( private array $approved_by_call_id ) {}

	public static function none(): self {
		return new self( [] );
	}

	/** From the browser's decoded JSON — only an explicit true counts as approval. */
	public static function from_request( mixed $posted ): self {
		$approved = [];

		foreach ( is_array( $posted ) ? $posted : [] as $call_id => $decision ) {
			$approved[ (string) $call_id ] = true === $decision;
		}

		return new self( $approved );
	}

	public function approves( string $call_id ): bool {
		return $this->approved_by_call_id[ $call_id ] ?? false;
	}
}
