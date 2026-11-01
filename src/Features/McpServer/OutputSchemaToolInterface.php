<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

interface OutputSchemaToolInterface {

	/**
	 * JSON Schema of a successful result, or null to publish none. A client may reject
	 * a result that does not conform, so this promises only what is certain.
	 *
	 * @return array<string, mixed>|null
	 */
	public function output_schema(): ?array;
}
