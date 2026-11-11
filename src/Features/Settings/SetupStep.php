<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

defined( 'ABSPATH' ) || exit;

/** One line of Overview's setup checklist: what to set, what is true now, and the one way to do it. */
final readonly class SetupStep {

	/** @param string|null $action_url Null when this person cannot do it, as a shop manager cannot change Access. */
	public function __construct(
		public string $title,
		public string $fact,
		public bool $done,
		public string $action_label,
		public ?string $action_url,
	) {}
}
