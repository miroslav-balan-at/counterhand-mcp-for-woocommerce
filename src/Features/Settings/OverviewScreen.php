<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Features\ActionLog\ActivityReportInterface;
use Counterhand\Features\Playground\TextModelInterface;
use Counterhand\Features\Tokens\Domain\ScopeGroups;
use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Overview: what is still to set up, then the store's exposure at a glance, from
 * what the other screens already hold.
 *
 * Calls are counted only while the action log records them. With it off the
 * table holds just the groups that are always logged, so a count would read as
 * a quiet store rather than an unwatched one.
 */
final readonly class OverviewScreen implements ScreenBodyInterface {

	private const FAILURES_LISTED = 5;

	public function __construct(
		private PluginSettings $settings,
		private ConnectionMatcher $matcher,
		private ActivityReportInterface $activity,
		private TextModelInterface $model,
		/** @var list<OverviewCardInterface> */
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
		$setup            = $this->setup( $can_save, $readable_groups, $live_connections );
		$activity         = $this->settings->is_action_log_enabled()
			? $this->activity->since( new \DateTimeImmutable( 'today', wp_timezone() ), self::FAILURES_LISTED )
			: null;

		require __DIR__ . '/views/overview.php';
	}

	/**
	 * What still has to be in place, each with the one action that does it.
	 *
	 * @return list<SetupStep>
	 */
	private function setup( bool $can_save, int $readable_groups, int $live_connections ): array {
		$access = $can_save ? AdminScreen::Access->url() : null;

		return [
			new SetupStep(
				__( 'Choose what AI may reach', 'counterhand-mcp-for-woocommerce' ),
				0 < $readable_groups
					/* translators: %s: number of store areas AI may read. */
					? sprintf( _n( '%s area can be read.', '%s areas can be read.', $readable_groups, 'counterhand-mcp-for-woocommerce' ), number_format_i18n( $readable_groups ) )
					: __( 'No area is switched on yet.', 'counterhand-mcp-for-woocommerce' ),
				0 < $readable_groups,
				__( 'Choose areas', 'counterhand-mcp-for-woocommerce' ),
				$access
			),
			new SetupStep(
				__( 'Connect an app', 'counterhand-mcp-for-woocommerce' ),
				0 < $live_connections
					/* translators: %s: number of connected AI apps. */
					? sprintf( _n( '%s app is connected.', '%s apps are connected.', $live_connections, 'counterhand-mcp-for-woocommerce' ), number_format_i18n( $live_connections ) )
					: __( 'Claude, ChatGPT or Cursor, in two steps.', 'counterhand-mcp-for-woocommerce' ),
				0 < $live_connections,
				__( 'Connect an app', 'counterhand-mcp-for-woocommerce' ),
				AdminScreen::Connect->url()
			),
			new SetupStep(
				__( 'Choose an AI model', 'counterhand-mcp-for-woocommerce' ),
				__( 'The chat, Rewrites and the weekly report need one.', 'counterhand-mcp-for-woocommerce' ),
				$this->model->is_connected(),
				__( 'Choose a model', 'counterhand-mcp-for-woocommerce' ),
				AdminScreen::Chat->url()
			),
			new SetupStep(
				__( 'Record activity', 'counterhand-mcp-for-woocommerce' ),
				__( 'See every call AI makes, with personal details masked.', 'counterhand-mcp-for-woocommerce' ),
				$this->settings->is_action_log_enabled(),
				__( 'Turn on', 'counterhand-mcp-for-woocommerce' ),
				$access
			),
		];
	}
}
