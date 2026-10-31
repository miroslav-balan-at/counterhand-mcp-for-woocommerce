<?php

declare( strict_types=1 );

namespace Counterhand\Features\Flamingo;

defined( 'ABSPATH' ) || exit;

final readonly class InboundMessagePage {

	/** @param list<InboundMessage> $messages */
	public function __construct(
		public array $messages,
		public int $total,
	) {}
}
