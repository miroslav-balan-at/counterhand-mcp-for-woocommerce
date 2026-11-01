<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;

defined( 'ABSPATH' ) || exit;

/** Where a reversible write is recorded. Owned here because the pipeline is what calls it. */
interface ChangeJournalInterface {

	/** A connection keeps a history only while it can reach this, so the store's switch and the grant both decide. */
	public const UNDO_TOOL = 'undo_change';

	/**
	 * @param  array<string, mixed> $before
	 * @param  array<string, mixed> $after
	 * @return string|null The id an agent undoes it by, or null if it could not be recorded.
	 */
	public function record( AuthenticatedAgent $agent, string $tool_name, ChangePlan $plan, array $before, array $after ): ?string;
}
