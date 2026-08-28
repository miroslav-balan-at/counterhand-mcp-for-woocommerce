<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * @package Counterhand
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/src/Autoloader.php';
Counterhand\Autoloader::register();

Counterhand\Uninstall::run();
