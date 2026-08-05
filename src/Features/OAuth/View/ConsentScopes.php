<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth\View;

use Counterhand\Features\Settings\PublishedScopes;
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

	/** @param list<ApiScope> $requested */
	public static function from( array $requested, PublishedScopes $published ): self {
		return new self(
			array_values(
				array_filter(
					array_map(
						static fn ( ToolSection $section ): ?ConsentSection => ConsentSection::from( $section, $requested, $published ),
						ToolSection::cases()
					)
				)
			)
		);
	}

	/** False when the store withholds everything the client asked for. */
	public function has_grantable(): bool {
		foreach ( $this->sections as $section ) {
			foreach ( $section->groups as $group ) {
				foreach ( $group->scopes as $scope ) {
					if ( $scope->available ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/** Whether any row carries a disabled box, so the hint under the legend earns its place. */
	public function has_withheld(): bool {
		foreach ( $this->sections as $section ) {
			foreach ( $section->groups as $group ) {
				foreach ( $group->scopes as $scope ) {
					if ( ! $scope->available ) {
						return true;
					}
				}
			}
		}

		return false;
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
