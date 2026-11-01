<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Features\Tokens\Domain\ScopeGroupInterface;

defined( 'ABSPATH' ) || exit;

/**
 * What the store itself permits, as the Chat tab needs to ask it.
 *
 * Playground asks this rather than importing the Settings class, so the chat
 * picker can tell the user their tick was overruled without this slice knowing
 * where the answer is stored.
 */
interface ChatToolPolicy {

	public function allows_read( ScopeGroupInterface $group ): bool;

	public function allows_write( ScopeGroupInterface $group ): bool;

	/** Where this user goes to change the answer for this area, or null when they cannot. */
	public function switch_for( ScopeGroupInterface $group ): ?StoreSwitchLink;
}
