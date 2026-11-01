<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/** The same routes reach the original, whose text and status products:write gates. */
final readonly class ExistingTranslationPolicy implements ArgumentPolicy {

	public function __construct(
		private TranslatedElements $elements,
		private bool $original_must_be_published = false,
	) {}

	public function verdict( array $arguments ): Verdict {
		$id       = $arguments['id'] ?? null;
		$elements = $this->elements->for_call( $arguments );
		$details  = is_numeric( $id ) ? $elements->details( (int) $id ) : null;

		if ( null === $details || ! $elements->is_translation( $details ) ) {
			return Verdict::deny( 'id must be a translation. An original cannot be changed through these tools.' );
		}

		if ( $this->original_must_be_published && 'publish' !== get_post_status( (int) $elements->id_in( $details, (string) $details->source_language_code ) ) ) {
			return Verdict::deny( 'Its original is not published, so this would put an unreleased product on sale. Publish the original first.' );
		}

		return Verdict::allow();
	}
}
