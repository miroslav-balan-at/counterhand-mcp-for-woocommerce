<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth;

defined( 'ABSPATH' ) || exit;

/**
 * Which discovery document a request is asking for.
 *
 * Resolved from the request path, not from our own query var: the
 * /.well-known/ rewrite is a shared namespace and another plugin can win the
 * rule (BackWPup bundles wp-media/mcp-oauth, which registers the same two
 * paths), in which case our query var is never set but the request is still
 * ours to answer.
 */
enum WellKnownDocument: string {

	case Resource = 'resource';
	case Server   = 'server';

	public static function from_path( string $path ): ?self {
		return match ( strtolower( trim( $path, '/' ) ) ) {
			'.well-known/oauth-protected-resource'   => self::Resource,
			'.well-known/oauth-authorization-server' => self::Server,
			default                                  => null,
		};
	}

	public static function try_from_query_var( mixed $value ): ?self {
		return is_string( $value ) ? self::tryFrom( $value ) : null;
	}
}
