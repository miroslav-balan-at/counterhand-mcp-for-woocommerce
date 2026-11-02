<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

final readonly class AppViewContents {

	public function __construct(
		public string $uri,
		public string $html,
	) {}
}
