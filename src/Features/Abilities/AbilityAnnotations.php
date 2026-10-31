<?php

declare( strict_types=1 );

namespace Counterhand\Features\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * An ability's annotations, read the way MCP's defaults read them: an absent or
 * null hint means not read-only, not idempotent.
 *
 * Every write reports destructive whatever it declares. Rank Math marks
 * set-global-seo-settings false, which can noindex a whole site, and WPForms marks
 * false a choices update that drops whatever is left out.
 */
final readonly class AbilityAnnotations {

	private function __construct(
		public bool $read_only,
		public bool $destructive,
		public bool $idempotent,
	) {}

	public static function of( \WP_Ability $ability ): self {
		$annotations = $ability->get_meta()['annotations'] ?? [];
		$read_only   = true === ( $annotations['readonly'] ?? null );

		$idempotent = true === ( $annotations['idempotent'] ?? null );

		return new self( $read_only, ! $read_only, $idempotent );
	}
}
