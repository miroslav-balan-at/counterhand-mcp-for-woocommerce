<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Features\Playground\Provider\ToolCall;

defined( 'ABSPATH' ) || exit;

/**
 * One model turn parked mid-flight: every tool it asked for, and which of
 * those wait on the administrator. Nothing in it has run yet.
 */
final readonly class PendingConfirmation {

	/**
	 * @param list<ToolCall> $calls     Every call of the turn, in the model's order.
	 * @param list<string>   $gated_ids The ids among them that need a person's approval.
	 */
	public function __construct(
		public string $key,
		public array $calls,
		public array $gated_ids,
	) {}

	public function needs_approval( ToolCall $call ): bool {
		return in_array( $call->id, $this->gated_ids, true );
	}

	/** @return array{key: string, gated: list<string>, calls: list<array{id: string, name: string, arguments: array<string, mixed>}>} */
	public function to_array(): array {
		return [
			'key'   => $this->key,
			'gated' => $this->gated_ids,
			'calls' => array_map(
				static fn ( ToolCall $call ): array => [
					'id'        => $call->id,
					'name'      => $call->name,
					'arguments' => $call->input,
				],
				$this->calls
			),
		];
	}

	/** A transient can come back as anything — a malformed payload yields nothing to resume. */
	public static function from_array( mixed $data ): ?self {
		if ( ! is_array( $data ) || ! is_string( $data['key'] ?? null ) || ! is_array( $data['gated'] ?? null ) || ! is_array( $data['calls'] ?? null ) ) {
			return null;
		}

		$calls = [];
		foreach ( $data['calls'] as $call ) {
			if ( ! is_array( $call ) || ! is_string( $call['id'] ?? null ) || ! is_string( $call['name'] ?? null ) || ! is_array( $call['arguments'] ?? null ) ) {
				return null;
			}

			$calls[] = new ToolCall( $call['id'], $call['name'], $call['arguments'] );
		}

		$gated = array_values( array_filter( $data['gated'], 'is_string' ) );

		return new self( $data['key'], $calls, $gated );
	}
}
