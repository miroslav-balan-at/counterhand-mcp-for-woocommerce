<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Settings;

defined( 'ABSPATH' ) || exit;

/** Backed values are the chip states connect.js switches on. */
enum ReadinessStatus: string {

	case Ok    = 'ok';
	case Local = 'local';
	case Error = 'error';
}
