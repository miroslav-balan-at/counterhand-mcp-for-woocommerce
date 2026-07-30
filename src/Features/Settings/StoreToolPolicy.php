<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Features\Playground\ChatToolPolicy;
use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * The store's group settings, answered for the Chat tab's picker.
 */
final readonly class StoreToolPolicy implements ChatToolPolicy {

	public function __construct( private PluginSettings $settings ) {}

	public function allows_read( ToolGroup $group ): bool {
		return $this->settings->is_group_read_enabled( $group );
	}

	public function allows_write( ToolGroup $group ): bool {
		return $this->settings->is_group_write_enabled( $group );
	}

	public function settings_url(): string {
		return AdminScreen::Settings->url();
	}
}
