<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Which of the two forms posted the settings option, and so which keys it owns.
 */
enum SettingsForm: string {

	case Store        = 'store';
	case Integrations = 'integrations';
}
