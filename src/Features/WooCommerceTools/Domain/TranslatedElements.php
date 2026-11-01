<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/** Products, variations or one taxonomy's terms, as WPML records their languages. */
final readonly class TranslatedElements {

	private function __construct(
		private ?string $post_type,
		private ?string $taxonomy,
	) {}

	public static function posts( string $post_type ): self {
		return new self( $post_type, null );
	}

	public static function terms( string $taxonomy ): self {
		return new self( null, $taxonomy );
	}

	/** The taxonomy depends on which attribute a call names. */
	public static function attribute_terms(): self {
		return new self( null, null );
	}

	/** @param array<string, mixed> $arguments */
	public function for_call( array $arguments ): self {
		if ( null !== $this->post_type || null !== $this->taxonomy ) {
			return $this;
		}

		return self::terms( wc_attribute_taxonomy_name_by_id( (int) ( $arguments['attribute_id'] ?? 0 ) ) );
	}

	/** Null when WPML records no language for it, which is also true of an id that does not exist. */
	public function details( int $id ): ?object {
		$element_id = $this->element_id( $id );

		if ( null === $element_id ) {
			return null;
		}

		$details = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $element_id,
				'element_type' => $this->type(),
			]
		);

		return is_object( $details ) && ! empty( $details->trid ) ? $details : null;
	}

	/** WooCommerce Multilingual records no source language for a term, so a term goes by the store's default. */
	public function is_translation( object $details ): bool {
		if ( null !== $this->post_type ) {
			return ! empty( $details->source_language_code );
		}

		return apply_filters( 'wpml_default_language', null ) !== ( $details->language_code ?? null );
	}

	public function has_language( object $details, string $language ): bool {
		$translations = apply_filters( 'wpml_get_element_translations', null, $details->trid, $this->type() );

		return is_array( $translations ) && isset( $translations[ $language ] );
	}

	/** The id of the same element in a language, or null when it has none. */
	public function id_in( object $details, string $language ): ?int {
		$translations = apply_filters( 'wpml_get_element_translations', null, $details->trid, $this->type() );
		$element      = is_array( $translations ) ? ( $translations[ $language ] ?? null ) : null;

		return is_object( $element ) && isset( $element->element_id ) ? (int) $element->element_id : null;
	}

	private function type(): string {
		return null !== $this->post_type ? 'post_' . $this->post_type : 'tax_' . $this->taxonomy;
	}

	private function element_id( int $id ): ?int {
		if ( null !== $this->post_type ) {
			return $id;
		}

		$term = get_term( $id, (string) $this->taxonomy );

		return $term instanceof \WP_Term ? $term->term_taxonomy_id : null;
	}
}
