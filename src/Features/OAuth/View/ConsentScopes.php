<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth\View;

use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * The whole consent screen's scope layout, arranged the way a person reads it.
 *
 * Built once in the endpoint and handed to the template, so consent.php asks
 * questions of an object rather than sorting a scope list into buckets in the
 * middle of markup.
 */
final readonly class ConsentScopes {

	/** @param list<ConsentSection> $sections */
	private function __construct(
		public array $sections,
	) {}

	/** @param list<ApiScope> $offered */
	public static function from( array $offered ): self {
		return new self(
			array_values(
				array_filter(
					array_map(
						static fn ( ToolSection $section ): ?ConsentSection => ConsentSection::from( $section, $offered ),
						ToolSection::cases()
					)
				)
			)
		);
	}

	/** Drives the "this app can change your store" warning above the buttons. */
	public function has_write(): bool {
		foreach ( $this->sections as $section ) {
			foreach ( $section->groups as $group ) {
				if ( $group->has_write() ) {
					return true;
				}
			}
		}

		return false;
	}
}
