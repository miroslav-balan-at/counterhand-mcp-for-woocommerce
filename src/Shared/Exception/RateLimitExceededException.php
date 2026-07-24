<?php

declare( strict_types=1 );

namespace AgentGateMcp\Shared\Exception;

defined( 'ABSPATH' ) || exit;

final class RateLimitExceededException extends \RuntimeException {

	public function __construct( public readonly int $retry_after_seconds ) {
		parent::__construct( 'Rate limit exceeded. Slow down and retry.' );
	}
}
