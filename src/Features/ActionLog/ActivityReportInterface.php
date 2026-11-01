<?php

declare( strict_types=1 );

namespace Counterhand\Features\ActionLog;

defined( 'ABSPATH' ) || exit;

/**
 * What the action log can say about recent calls, for a screen that summarizes it.
 */
interface ActivityReportInterface {

	public function since( \DateTimeImmutable $start, int $failures_to_list ): ActivitySummary;
}
