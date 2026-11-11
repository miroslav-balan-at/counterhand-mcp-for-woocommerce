<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Features\Tokens\Domain\ScopeGroups;
use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * What AI may read and change: AI access, the store's areas, other plugins, limits
 * and Activity, on one screen. Store areas and other plugins stay two forms
 * (SettingsForm), so saving one never resets the other.
 */
final readonly class AccessScreen implements ScreenBodyInterface {

	public function __construct( private PluginSettings $settings ) {}

	public function render_body(): void {
		$settings         = $this->settings;
		$can_save         = SettingsAccess::can_save();
		$other_mcp_server = OtherMcpServer::is_running();
		$integrations     = ScopeGroups::integrations();
		$supported        = ScopeGroups::supported_but_inactive();
		$tool_sections    = array_filter(
			ToolSection::populated(),
			static fn ( ToolSection $section ): bool => ToolSection::Integrations !== $section
		);

		require __DIR__ . '/views/other-server-notice.php';
		require __DIR__ . '/views/read-only-notice.php';
		require __DIR__ . '/views/settings.php';
		require __DIR__ . '/views/integrations.php';
	}
}
