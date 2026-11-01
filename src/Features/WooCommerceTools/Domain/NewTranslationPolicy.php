<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * A second translation into one language takes the first one's place and leaves it
 * orphaned, as a success. A term with no language throws only after the new term exists.
 */
final readonly class NewTranslationPolicy implements ArgumentPolicy {

	public function __construct( private TranslatedElements $elements ) {}

	public function verdict( array $arguments ): Verdict {
		$language = $arguments['lang'] ?? null;
		$source   = $arguments['translation_of'] ?? null;

		if ( ! is_string( $language ) || ! is_numeric( $source ) ) {
			return Verdict::deny( 'lang and translation_of are both required.' );
		}

		if ( true !== apply_filters( 'wpml_language_is_active', false, $language ) ) {
			return Verdict::deny( sprintf( '"%s" is not an active language on this store.', $language ) );
		}

		$elements = $this->elements->for_call( $arguments );
		$details  = $elements->details( (int) $source );

		if ( null === $details ) {
			return Verdict::deny( sprintf( '%d does not exist, or WPML records no language for it, so it cannot be translated. An administrator can set its language in the editor.', (int) $source ) );
		}

		if ( $elements->has_language( $details, $language ) ) {
			return Verdict::deny( sprintf( '%d already has a "%s" translation, which a second one would replace and orphan. Update that one instead; its id is in translations.', (int) $source, $language ) );
		}

		return Verdict::allow();
	}
}
