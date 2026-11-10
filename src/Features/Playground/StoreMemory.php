<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

defined( 'ABSPATH' ) || exit;

/**
 * What the owner wants every chat to know, such as house style or which supplier is late.
 * Its own option, so saving a model never touches it.
 */
final readonly class StoreMemory {

	public const OPTION     = 'counterhand_store_memory';
	public const MAX_LENGTH = 2000;

	public function text(): string {
		return (string) get_option( self::OPTION, '' );
	}

	public function save( string $text ): void {
		update_option( self::OPTION, mb_substr( trim( sanitize_textarea_field( $text ) ), 0, self::MAX_LENGTH ), false );
	}
}
