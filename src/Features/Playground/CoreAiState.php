<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Playground;

defined( 'ABSPATH' ) || exit;

/** Where the site is on the road to a WordPress-managed model. */
enum CoreAiState {

	case Ready;
	case NeedsKey;
	case NeedsProvider;
}
