<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;

defined( 'ABSPATH' ) || exit;

/** A tool whose answer depends on who is asking, beyond the scope that lets them in. */
interface AgentAwareToolInterface {

	/**
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	public function execute_as( AuthenticatedAgent $agent, array $arguments ): array;
}
