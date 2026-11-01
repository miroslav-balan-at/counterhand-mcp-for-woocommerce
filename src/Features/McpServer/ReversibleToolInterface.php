<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

/**
 * A write whose effect can be previewed, and put back.
 *
 * The dispatcher asks this rather than a tool's type, so dry-run and undo reach every
 * transport at once and a tool only has to say what it touches.
 */
interface ReversibleToolInterface {

	public function is_reversible(): bool;

	/** Whether a change can also be put back, or only previewed. */
	public function is_undoable(): bool;

	/**
	 * Every field a write can change, so that what a call changes without being asked,
	 * such as the stock a switched-off manage_stock clears, is put back too.
	 *
	 * @return list<string>
	 */
	public function watched_fields(): array;

	/**
	 * What a call with these arguments would touch.
	 *
	 * @param array<string, mixed> $arguments
	 * @throws ToolCallException When the tool's own rules refuse the call, as they would for real.
	 */
	public function change_plan( array $arguments ): ChangePlan;

	/**
	 * The named fields as stored now.
	 *
	 * @return array<string, mixed>
	 * @throws ToolCallException When the record cannot be read.
	 */
	public function read_state( ChangePlan $plan ): array;

	/** The plan with the id a successful create returned, or null if it returned none. @param array<string, mixed> $result */
	public function with_result( ChangePlan $plan, array $result ): ?ChangePlan;

	/**
	 * Puts a change back through the same route, so validation and side effects still run.
	 *
	 * @param array<string, mixed> $before
	 * @throws ToolCallException When the store refuses.
	 */
	public function restore( ChangePlan $plan, array $before ): void;
}
