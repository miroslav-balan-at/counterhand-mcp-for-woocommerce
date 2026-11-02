<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;

defined( 'ABSPATH' ) || exit;

/**
 * Interactive views a host can show beside a tool's result.
 *
 * Separate from ResourceSourceInterface because the MCP Apps spec lets them stay out
 * of resources/list: they are of no use to a client that would only offer them as files.
 */
interface AppViewSourceInterface {

	public function uri_for_tool( string $tool_name ): ?string;

	/** The view, or null for a URI this connection cannot read, so the two cannot be told apart. */
	public function read( string $uri, AuthenticatedAgent $agent ): ?AppViewContents;
}
