<?php

declare( strict_types=1 );

namespace Counterhand\Shared;

defined( 'ABSPATH' ) || exit;

/** Text a model reads stays in English, including any label it quotes, whatever the site's language. */
final class ModelFacingText {

	/** @param callable(): string $build */
	public static function in_english( callable $build ): string {
		$switched = switch_to_locale( 'en_US' );

		try {
			return $build();
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}
}
