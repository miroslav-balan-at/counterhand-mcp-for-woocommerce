<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth;

defined( 'ABSPATH' ) || exit;

/**
 * Which discovery document a request is asking for.
 *
 * Resolved from the request path, not from our own query var: the
 * /.well-known/ rewrite is a shared namespace and another plugin can win the
 * rule (BackWPup and Rank Math bundle wp-media/mcp-oauth, which registers the same
 * two paths), in which case our query var is never set but the request is still
 * ours to answer. Its own 401 points at the same address, so its connector gets our
 * document and may fail; the settings screen says so, and only one can own the path.
 */
enum WellKnownDocument: string {

	case Resource = 'resource';
	case Server   = 'server';

	public static function from_path( string $path ): ?self {
		return match ( strtolower( trim( $path, '/' ) ) ) {
			'.well-known/oauth-protected-resource'   => self::Resource,
			'.well-known/oauth-authorization-server' => self::Server,
			// The one discovery address the spec gives a store in a subfolder that sits inside
			// WordPress. Answered from the path, so it needs no rewrite on a root install.
			'.well-known/openid-configuration'       => self::is_in_subfolder() ? self::Server : null,
			default                                  => null,
		};
	}

	private static function is_in_subfolder(): bool {
		return '' !== trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	}

	public static function try_from_query_var( mixed $value ): ?self {
		return is_string( $value ) ? self::tryFrom( $value ) : null;
	}
}
