<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Which custom fields of a product translation these tools may touch.
 *
 * WPML says which fields are translated rather than copied, which also keeps out
 * anything Multilingual would overwrite from the original. But its list has fields that
 * are not text, and a bad value in one costs money: a translated download file or external
 * link points customers somewhere else. SEO fields go through the Yoast and Rank Math
 * integrations, whose policies refuse a template that reads private meta.
 */
final readonly class TranslatableMetaRules {

	/** WPML_TRANSLATE_CUSTOM_FIELD, which is not defined until WPML has loaded. */
	private const TRANSLATE_MODE = 2;

	private const NOT_TEXT = [ '_file_paths', '_downloadable_files', '_product_url', '_children', '_crosssell_ids', '_upsell_ids', '_warranty_attachment_id', '_safety_attachment_ids' ];

	private const SEO_PREFIX = '/^(_yoast_wpseo_|rank_math_)/';

	public function __construct( private MetaKeyPolicy $meta_policy ) {}

	/** @return list<string> */
	public function keys(): array {
		$settings = apply_filters( 'wpml_setting', null, 'translation-management' );
		$modes    = is_array( $settings ) ? ( $settings['custom_fields_translation'] ?? [] ) : [];

		$keys = array_keys( array_filter( (array) $modes, static fn ( mixed $mode ): bool => self::TRANSLATE_MODE === (int) $mode ) );

		return array_values( array_filter( array_map( 'strval', $keys ), [ $this, 'is_allowed' ] ) );
	}

	private function is_allowed( string $key ): bool {
		return ! in_array( $key, self::NOT_TEXT, true )
			&& 1 !== preg_match( self::SEO_PREFIX, $key )
			&& ! $this->meta_policy->is_off_limits( $key );
	}
}
