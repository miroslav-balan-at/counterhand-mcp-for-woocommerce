<?php

declare( strict_types=1 );

namespace AgentGateMcp;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal PSR-4 autoloader for the AgentGateMcp namespace.
 *
 * Deliberately dependency-free: the plugin ships no Composer runtime
 * vendor directory to avoid cross-plugin dependency collisions.
 */
final class Autoloader {

	private const PREFIX = 'AgentGateMcp\\';

	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	public static function load( string $class_name ): void {
		if ( ! str_starts_with( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$path     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
}
