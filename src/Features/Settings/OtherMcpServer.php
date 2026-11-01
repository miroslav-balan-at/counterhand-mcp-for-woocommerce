<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

defined( 'ABSPATH' ) || exit;

final readonly class OtherMcpServer {

	/** A route namespace, not a plugin name: several plugins bundle the adapter. */
	public static function is_running(): bool {
		return in_array( 'mcp', rest_get_server()->get_namespaces(), true );
	}
}
