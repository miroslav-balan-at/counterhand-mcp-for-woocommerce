<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Whether options.php will accept the settings forms from this user.
 *
 * The screens open at manage_woocommerce, but saving stays with administrators,
 * because it decides what every connection can reach. Shop managers read them.
 */
final readonly class SettingsAccess {

	public const OPTION_GROUP = 'counterhand_settings_group';

	public static function can_save(): bool {
		// The same question options.php asks, so a site's own filter is answered here too.
		return current_user_can( (string) apply_filters( 'option_page_capability_' . self::OPTION_GROUP, 'manage_options' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.Capabilities.Undetermined -- core's own filter.
	}
}
