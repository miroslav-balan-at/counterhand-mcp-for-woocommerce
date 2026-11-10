<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Features\ActionLog\ActivityReportInterface;
use Counterhand\Features\Tokens\Domain\ScopeGroups;
use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * The store's exposure at a glance, from what the other screens already hold.
 *
 * Calls are counted only while the action log records them. With it off the
 * table holds just the groups that are always logged, so a count would read as
 * a quiet store rather than an unwatched one.
 */
final readonly class DashboardScreen implements ScreenBodyInterface {

	private const FAILURES_LISTED = 5;

	public function __construct(
		private PluginSettings $settings,
		private ConnectionMatcher $matcher,
		private ActivityReportInterface $activity,
		/** @var list<DashboardCardInterface> */
		private array $cards = [],
	) {}

	public function render_body(): void {
		$settings         = $this->settings;
		$can_save         = SettingsAccess::can_save();
		$is_enabled       = $this->settings->is_enabled();
		$live_connections = $this->matcher->live_count();
		$readable_groups  = count( array_filter( ToolGroup::cases(), $this->settings->is_group_read_enabled( ... ) ) );
		$writable_groups  = array_values( array_filter( ToolGroup::cases(), $this->settings->is_group_write_enabled( ... ) ) );
		$integrations     = ScopeGroups::integrations();
		$other_mcp_server = OtherMcpServer::is_running();
		$cards            = $this->cards;
		$activity         = $this->settings->is_action_log_enabled()
			? $this->activity->since( new \DateTimeImmutable( 'today', wp_timezone() ), self::FAILURES_LISTED )
			: null;

		require __DIR__ . '/views/dashboard.php';
	}
}
