<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OAuth\View;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\ToolSection;

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

	/**
	 * Null when nothing the client asked for falls in this section, which is
	 * how the screen shows only the sections that are actually in play.
	 *
	 * @param  list<ApiScope> $offered
	 */
	public static function from( ToolSection $section, array $offered ): ?self {
		$groups = array_values(
			array_filter(
				array_map(
					static fn ( $group ): ?ConsentGroup => ConsentGroup::from( $group, $offered ),
					$section->groups()
				)
			)
		);

		if ( [] === $groups ) {
			return null;
		}

		return new self( $section, $groups );
	}

	/** Advanced sections render inside a collapsed disclosure. */
	public function is_collapsed(): bool {
		return $this->section->is_advanced();
	}
}
