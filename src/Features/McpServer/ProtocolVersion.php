<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

/**
 * The protocol revisions this server speaks, in its two eras.
 *
 * Legacy revisions (2025-11-25 and earlier) are negotiated once through the
 * initialize handshake. Modern ones (2026-07-28 and later) are named on every
 * request in params._meta, with no handshake at all.
 */
final class ProtocolVersion {

	/** The first revision of the stateless era; revision identifiers are dates, so they order as strings. */
	public const FIRST_MODERN = '2026-07-28';

	/** What initialize answers with when the client asks for a version this server does not know. */
	public const LATEST_LEGACY = '2025-11-25';

	public const LEGACY = [ '2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05' ];

	/** Only modern revisions: legacy ones are reached through initialize, not named per request. */
	public const MODERN = [ '2026-07-28' ];

	public static function is_modern( string $version ): bool {
		return strcmp( $version, self::FIRST_MODERN ) >= 0;
	}
}
