<?php

declare( strict_types=1 );

namespace Counterhand\Shared\Tool;

use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

/**
 * An MCP tool. Implementations carry their own protocol metadata and
 * are gated by exactly one scope.
 */
interface ToolInterface {

	public function name(): string;

	/** Description written for AI agents: units, defaults, pagination behavior. */
	public function description(): string;

	/** JSON Schema (draft 2020-12 subset understood by WP core validators). */
	public function input_schema(): array;

	public function required_scope(): ApiScope;

	public function group(): ToolGroup;

	/**
	 * False when the current user's capabilities — or a missing endpoint — make
	 * this tool unusable.
	 *
	 * The third gate, after store policy and token scope, and the only one that
	 * asks WordPress rather than this plugin's own settings. It is deliberately
	 * a plain boolean: how a tool decides is its own business, and expressing
	 * the decision as, say, a REST route to probe would drag the WP REST router
	 * into a contract that McpServer, Playground and Settings all import.
	 *
	 * Advisory, not enforcement. Answering true does not authorize anything —
	 * every dispatch still runs WooCommerce's own permission callback, with the
	 * resource id in hand, which this cannot have.
	 */
	public function is_available(): bool;

	/**
	 * @param array $arguments Already schema-sanitized and validated arguments.
	 * @throws ToolCallException On any execution failure, with an agent-actionable message.
	 */
	public function execute( array $arguments ): array;
}
