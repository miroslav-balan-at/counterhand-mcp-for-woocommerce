<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

final readonly class PromptArgument {

	/**
	 * @param string $pattern What a value must match. It goes into text a model acts on, so
	 *                        free text is never accepted: that would be an injection point.
	 */
	public function __construct(
		public string $name,
		public string $description,
		public string $pattern,
		public bool $required = false,
		public string $default_value = '',
	) {}
}
