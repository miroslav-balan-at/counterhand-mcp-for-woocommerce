<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Tokens\Domain\GrantedScopeSet;

defined( 'ABSPATH' ) || exit;

/**
 * Which integration tools a set of scopes reaches right now, for the person
 * approving them. A grant records this list, so what a plugin adds in a later update
 * stays out of it until someone approves it.
 */
interface IntegrationToolCatalogInterface {

	/**
	 * @return array<string, list<string>> Scope key => tool names, for the current user.
	 */
	public function integration_tools_by_scope( GrantedScopeSet $scopes ): array;
}
