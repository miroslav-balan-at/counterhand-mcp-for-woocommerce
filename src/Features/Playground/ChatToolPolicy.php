<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * What the store itself permits, as the Chat tab needs to ask it.
 *
 * Playground asks this rather than importing the Settings class, so the chat
 * picker can tell the user their tick was overruled without this slice knowing
 * where the answer is stored.
 */
interface ChatToolPolicy {

	public function allows_read( ToolGroup $group ): bool;

	public function allows_write( ToolGroup $group ): bool;

	/** Where an administrator goes to change the answer. */
	public function settings_url(): string;
}
