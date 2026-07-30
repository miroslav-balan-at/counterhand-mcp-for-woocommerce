<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Token counts for one or more model turns, best effort.
 */
final readonly class TokenUsage {

	public function __construct(
		public int $input = 0,
		public int $output = 0,
	) {}

	public function plus( self $other ): self {
		return new self( $this->input + $other->input, $this->output + $other->output );
	}

	/** @return array{input: int, output: int} For the wp_send_json_* edge only. */
	public function to_array(): array {
		return [
			'input'  => $this->input,
			'output' => $this->output,
		];
	}
}
