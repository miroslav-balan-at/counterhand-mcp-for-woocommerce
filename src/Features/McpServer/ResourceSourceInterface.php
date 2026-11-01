<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;

defined( 'ABSPATH' ) || exit;

interface ResourceSourceInterface {

	/** @return list<ResourceDefinition> Only what this connection may read, in a stable order. */
	public function resources_for( AuthenticatedAgent $agent ): array;

	/** The contents, or null for a URI this connection cannot read, so the two cannot be told apart. */
	public function read( string $uri, AuthenticatedAgent $agent ): ?string;
}
