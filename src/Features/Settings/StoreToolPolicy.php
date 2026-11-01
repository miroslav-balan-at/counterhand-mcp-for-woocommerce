<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Features\Playground\ChatToolPolicy;
use Counterhand\Features\Playground\StoreSwitchLink;
use Counterhand\Features\Tokens\Domain\ScopeGroupInterface;
use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * The store's group settings, answered for the Chat tab's picker.
 */
final readonly class StoreToolPolicy implements ChatToolPolicy {

	public function __construct( private PluginSettings $settings ) {}

	public function allows_read( ScopeGroupInterface $group ): bool {
		return $this->settings->is_group_read_enabled( $group );
	}

	public function allows_write( ScopeGroupInterface $group ): bool {
		return $this->settings->is_group_write_enabled( $group );
	}

	public function switch_for( ScopeGroupInterface $group ): ?StoreSwitchLink {
		if ( ! SettingsAccess::can_save() ) {
			return null;
		}

		$screen = ToolSection::Integrations === $group->section() ? AdminScreen::Integrations : AdminScreen::Settings;

		return new StoreSwitchLink( $screen->url(), $screen->menu_title() );
	}
}
