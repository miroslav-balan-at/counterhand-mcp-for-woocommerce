<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * The store's own switches: the connector, its tool groups, limits and the log.
 */
final readonly class SettingsScreen implements ScreenBodyInterface {

	public function __construct( private PluginSettings $settings ) {}

	public function render_body(): void {
		$settings         = $this->settings;
		$can_save         = SettingsAccess::can_save();
		$other_mcp_server = OtherMcpServer::is_running();
		$tool_sections    = array_filter(
			ToolSection::populated(),
			static fn ( ToolSection $section ): bool => ToolSection::Integrations !== $section
		);

		require __DIR__ . '/views/settings.php';
	}
}
