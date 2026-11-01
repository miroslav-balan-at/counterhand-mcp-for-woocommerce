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

	/** Answers whether this request will run tools, for a plugin that sets up per URL. */
	public const SERVES_TOOLS_FILTER = 'counterhand_serves_tools_on_this_request';

	/** @return list<ToolInterface> */
	public function visible_for( AuthenticatedAgent $agent ): array;

	/**
	 * The arguments a caller should offer a model for this tool: its own schema
	 * plus what the pipeline itself requires of a call.
	 *
	 * @return array<string, mixed>
	 */
	public function published_schema( ToolInterface $tool ): array;

	/**
	 * How many tools each group holds, before any gate is applied.
	 *
	 * The denominator an admin screen needs to explain what a setting is
	 * withholding — a visible-only count cannot say what is missing.
	 *
	 * @return array<string, int> Group slug => tool count.
	 */
	public function tool_counts_by_group(): array;

	/** The tool as this connection can reach it now, or null if it no longer can. */
	public function reversible_for( AuthenticatedAgent $agent, string $tool_name ): ?ReversibleToolInterface;

	/**
	 * Puts a change back, in the one place scope and the audit hook run. Its values came
	 * from the route, in the shape the route returns, so they skip argument validation.
	 *
	 * @param array<string, mixed> $before
	 * @throws \Counterhand\Shared\Exception\ToolCallException When the tool is out of reach, or the store refuses.
	 */
	public function restore( AuthenticatedAgent $agent, string $tool_name, ChangePlan $plan, array $before ): void;

	/**
	 * Whether this call, with these arguments, needs a person's agreement first.
	 *
	 * @param array<string, mixed> $arguments
	 */
	public function needs_confirmation( ToolInterface $tool, array $arguments ): bool;

	/**
	 * Never throws: every way a call can go wrong is an outcome, because the
	 * caller is mid-conversation and needs something to hand back to the model.
	 *
	 * @param array<string, mixed> $arguments Raw arguments as the caller received them.
	 */
	public function dispatch( string $tool_name, array $arguments, AuthenticatedAgent $agent ): DispatchOutcome;
}
