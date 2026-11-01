<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

defined( 'ABSPATH' ) || exit;

/**
 * The admin screen holding the store's switch for an area.
 */
final readonly class StoreSwitchLink {

	public function __construct(
		public string $url,
		public string $screen_title,
	) {}
}
