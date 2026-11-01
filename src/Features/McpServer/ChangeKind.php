<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

enum ChangeKind: string {
	case Updated = 'updated';
	case Created = 'created';
}
