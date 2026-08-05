<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth\View;

use Counterhand\Features\Settings\PublishedScopes;
use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * A heading on the consent screen and the group rows under it.
 *
 * The same sectioning the settings tab uses, so an admin who has configured the
 * store's groups meets the identical shape when approving a client.
 */
final readonly class ConsentSection {

	/** @param list<ConsentGroup> $groups */
	private function __construct(
		public ToolSection $section,
		public array $groups,
	) {}

	/** @param list<ApiScope> $requested */
	public static function from( ToolSection $section, array $requested, PublishedScopes $published ): self {
		return new self(
			$section,
			array_map(
				static fn ( $group ): ConsentGroup => ConsentGroup::from( $group, $requested, $published ),
				$section->groups()
			)
		);
	}

	/** Advanced sections render inside a collapsed disclosure. */
	public function is_collapsed(): bool {
		return $this->section->is_advanced();
	}
}
