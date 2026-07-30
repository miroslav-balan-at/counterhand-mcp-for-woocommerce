<?php

declare( strict_types=1 );

namespace Counterhand\Shared;

defined( 'ABSPATH' ) || exit;

/**
 * The store's own visual mark.
 *
 * Used on the OAuth consent pages, where it is the strongest trust signal, and
 * as the assistant's avatar in the admin chat — the same store, so the same
 * mark. Square site icon first, then the theme's custom logo; when neither
 * exists callers fall back to the lettermark, which needs no HTTP request and
 * therefore can never fail to load.
 */
final class StoreMark {

	public static function url(): ?string {
		$site_icon = get_site_icon_url( 96 );
		if ( is_string( $site_icon ) && '' !== $site_icon ) {
			return $site_icon;
		}

		$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id > 0 ) {
			$custom_logo = wp_get_attachment_image_url( $custom_logo_id, 'medium' );
			if ( is_string( $custom_logo ) && '' !== $custom_logo ) {
				return $custom_logo;
			}
		}

		return null;
	}

	/** First character of the store name, uppercased, for the lettermark. */
	public static function letter(): string {
		$name = (string) get_bloginfo( 'name' );

		return '' === $name ? '?' : mb_strtoupper( mb_substr( $name, 0, 1 ) );
	}
}
