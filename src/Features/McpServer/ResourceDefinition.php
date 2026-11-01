<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

final readonly class ResourceDefinition {

	public function __construct(
		public string $uri,
		public string $name,
		public string $title,
		public string $description,
		public string $mime_type,
	) {}
}
