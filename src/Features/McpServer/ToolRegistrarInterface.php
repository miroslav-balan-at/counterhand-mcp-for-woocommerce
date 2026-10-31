<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Where a feature hands over its tools.
 */
interface ToolRegistrarInterface {

	public function add( ToolInterface $tool ): void;

	/**
	 * Tools that cannot be known at boot and are not worth knowing on every page.
	 *
	 * @param callable(): iterable<ToolInterface> $source
	 */
	public function add_late_source( callable $source ): void;
}
