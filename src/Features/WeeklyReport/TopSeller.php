<?php

declare( strict_types=1 );

namespace Counterhand\Features\WeeklyReport;

defined( 'ABSPATH' ) || exit;

final readonly class TopSeller {

	public function __construct(
		public string $name,
		public int $quantity,
	) {}
}
