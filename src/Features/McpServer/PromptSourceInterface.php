<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;

defined( 'ABSPATH' ) || exit;

interface PromptSourceInterface {

	/** @return list<PromptDefinition> Only what this connection could carry out, in a stable order. */
	public function prompts_for( AuthenticatedAgent $agent ): array;
}
