<?php

declare( strict_types=1 );

namespace Counterhand\Features\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * An ability's annotations, read the way MCP's defaults read them: an absent or
 * null hint means not read-only, destructive, not idempotent. Yoast sends null
 * for destructive on purpose, because it cannot claim otherwise.
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

		return new self(
			read_only: $read_only,
			destructive: ! $read_only && false !== ( $annotations['destructive'] ?? null ),
			idempotent: true === ( $annotations['idempotent'] ?? null ),
		);
	}
}
