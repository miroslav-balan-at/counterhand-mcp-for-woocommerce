<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * The outcome of one tool call, ready to be carried back to the model.
 */
final readonly class ToolResult {

	public function __construct(
		public string $id,
		public string $name,
		public string $output,
		public bool $is_error,
	) {}
}
