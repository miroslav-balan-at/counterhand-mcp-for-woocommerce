<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * One tool invocation the model asked for, normalised across providers.
 */
final readonly class ToolCall {

	/** @param array<string,mixed> $input */
	public function __construct(
		public string $id,
		public string $name,
		public array $input,
	) {}
}
