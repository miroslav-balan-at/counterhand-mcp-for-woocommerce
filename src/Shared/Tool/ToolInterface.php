<?php

declare( strict_types=1 );

namespace AgentGateMcp\Shared\Tool;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Exception\ToolCallException;

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
	 * @param array $arguments Already schema-sanitized and validated arguments.
	 * @throws ToolCallException On any execution failure, with an agent-actionable message.
	 */
	public function execute( array $arguments ): array;
}
