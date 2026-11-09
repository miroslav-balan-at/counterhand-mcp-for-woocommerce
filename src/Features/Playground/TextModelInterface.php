<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

/** The model connected to the chat, for features that only need it to write text. */
interface TextModelInterface {

	public function is_connected(): bool;

	/**
	 * @throws ToolCallException When the provider refuses or cannot be reached.
	 */
	public function write( string $system_prompt, string $request, int $max_tokens ): string;
}
