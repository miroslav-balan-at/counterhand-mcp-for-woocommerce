<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * In-process access to the gated tool surface.
 *
 * The seam that lets another feature run tools without speaking MCP: an
 * implementation owns the whole pipeline — visibility, schema defaults,
 * validation, the scope gate, execution and the audit hook — so every caller
 * gets exactly what an external MCP client gets, minus the wire format.
 */
interface ToolDispatcherInterface {

	/** @return list<ToolInterface> */
	public function visible_for( AuthenticatedAgent $agent ): array;

	/**
	 * How many tools each group holds, before any gate is applied.
	 *
	 * The denominator an admin screen needs to explain what a setting is
	 * withholding — a visible-only count cannot say what is missing.
	 *
	 * @return array<string, int> Group slug => tool count.
	 */
	public function tool_counts_by_group(): array;

	/**
	 * Never throws: every way a call can go wrong is an outcome, because the
	 * caller is mid-conversation and needs something to hand back to the model.
	 *
	 * @param array<string, mixed> $arguments Raw arguments as the caller received them.
	 */
	public function dispatch( string $tool_name, array $arguments, AuthenticatedAgent $agent ): DispatchOutcome;
}
