<?php

declare( strict_types=1 );

namespace Counterhand\Features\ActionLog;

defined( 'ABSPATH' ) || exit;

final readonly class LoggedFailure {

	public function __construct(
		public string $tool_name,
		public string $connection,
		public \DateTimeImmutable $happened_at,
	) {}
}
