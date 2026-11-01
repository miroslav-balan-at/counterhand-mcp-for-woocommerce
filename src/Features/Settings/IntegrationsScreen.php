<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Features\Tokens\Domain\ScopeGroups;

defined( 'ABSPATH' ) || exit;

/**
 * One card per plugin this store could offer to AI clients, each switched on apart.
 */
final readonly class IntegrationsScreen implements ScreenBodyInterface {

	public function __construct( private PluginSettings $settings ) {}

	public function render_body(): void {
		$settings         = $this->settings;
		$can_save         = SettingsAccess::can_save();
		$integrations     = ScopeGroups::integrations();
		$other_mcp_server = OtherMcpServer::is_running();

		require __DIR__ . '/views/integrations.php';
	}
}
