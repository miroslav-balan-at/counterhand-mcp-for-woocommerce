<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * lang=all lifts WPML's language filters and Multilingual never puts them back, which is
 * harmless over HTTP. Several tool calls can share one request, as in a chat turn, and
 * every later list would then show all languages. An unknown language would also throw.
 */
final readonly class TranslationListPolicy implements ArgumentPolicy {

	public function verdict( array $arguments ): Verdict {
		$language = $arguments['lang'] ?? null;

		if ( null === $language ) {
			return Verdict::allow();
		}

		return true === apply_filters( 'wpml_language_is_active', false, $language )
			? Verdict::allow()
			: Verdict::deny( sprintf( '"%s" is not an active language on this store. Ask for one active language at a time; all is not offered here.', (string) $language ) );
	}
}
