<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

interface OpenArgumentsToolInterface {

	/**
	 * JSON Schema of every argument the tool accepts, including those its published
	 * schema leaves out to stay small. The pipeline reads it only to know which of
	 * those arrive as JSON text when they should be a list or an object.
	 *
	 * @return array<string, mixed>
	 */
	public function accepted_arguments_schema(): array;
}
