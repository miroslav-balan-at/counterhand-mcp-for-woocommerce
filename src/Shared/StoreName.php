<?php

declare( strict_types=1 );

namespace Counterhand\Shared;

defined( 'ABSPATH' ) || exit;

/**
 * The store's name as plain text. WordPress stores it HTML-escaped, so "Linden & Co."
 * reached apps and models as "Linden &amp; Co." wherever it went out as text or JSON.
 * Markup keeps using get_bloginfo( 'name' ) with its own escaping.
 */
final class StoreName {

	public static function plain(): string {
		return html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}
