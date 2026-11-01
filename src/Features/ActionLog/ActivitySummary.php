<?php

declare( strict_types=1 );

namespace Counterhand\Features\ActionLog;

defined( 'ABSPATH' ) || exit;

final readonly class ActivitySummary {

	/** @param list<LoggedFailure> $recent_failures Newest first. */
	public function __construct(
		public int $calls,
		public int $failures,
		public array $recent_failures,
	) {}
}
