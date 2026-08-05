<?php

declare( strict_types=1 );

namespace Counterhand\Features\ActionLog;

use Counterhand\Features\ActionLog\Persistence\LogSchema;
use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Settings\SettingsTabInterface;
use Counterhand\Shared\FeatureInterface;
use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Opt-in audit log: subscribes to tool calls, purges by retention, admin tab.
 */
final readonly class ActionLogFeature implements FeatureInterface, SettingsTabInterface {

	private const PURGE_HOOK = 'counterhand_purge_log';

	private ActionLogger $logger;

	public function __construct( private PluginSettings $settings ) {
		$this->logger = new ActionLogger();
	}

	public function register(): void {
		add_action( 'admin_init', [ LogSchema::class, 'maybe_upgrade' ] );
		add_action( self::PURGE_HOOK, [ $this, 'purge_expired' ] );
		add_action( 'admin_post_counterhand_clear_log', [ $this, 'handle_clear' ] );

		// Subscribed whatever the setting says: some groups are logged
		// unconditionally, and the callback is what decides.
		add_action( 'counterhand_tool_called', [ $this, 'maybe_log' ], 10, 5 );

		if ( ! $this->settings->is_action_log_enabled() ) {
			return;
		}

		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::PURGE_HOOK );
		}
	}

	/**
	 * Writes the row when logging is on, and also when the group demands it.
	 *
	 * Turning the log off is a reasonable choice about routine traffic — a store
	 * that lists products all day does not need a row per call. It is not a
	 * reasonable choice about running a maintenance routine against the live
	 * database, and a store owner who finds their roles reset deserves a record
	 * of what asked for it. So those groups are logged regardless, and the
	 * setting governs everything else.
	 *
	 * @param array<string, mixed> $arguments
	 */
	public function maybe_log( string $tool_name, string $token_label, bool $is_error, array $arguments, string $group = '' ): void {
		$always = ToolGroup::tryFrom( $group )?->is_always_logged() ?? false;

		if ( ! $always && ! $this->settings->is_action_log_enabled() ) {
			return;
		}

		$this->logger->log( $tool_name, $token_label, $is_error, $arguments );
	}

	public function purge_expired(): void {
		global $wpdb;

		$retention_days = $this->settings->log_retention_days();
		$table_name     = LogSchema::table_name();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-owned.
				gmdate( 'Y-m-d H:i:s', time() - $retention_days * DAY_IN_SECONDS )
			)
		);
	}

	public function handle_clear(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to clear the log.', 'counterhand-mcp-for-woocommerce' ) );
		}

		check_admin_referer( 'counterhand_clear_log' );

		global $wpdb;
		$table_name = LogSchema::table_name();

		$wpdb->query( "TRUNCATE TABLE {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name is plugin-owned, no user input.

		wp_safe_redirect(
			add_query_arg(
				[
					'page'                => 'counterhand-mcp-log',
					'counterhand_cleared' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render_tab(): void {
		global $wpdb;

		$table_name = LogSchema::table_name();

		$entries = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 100", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name is plugin-owned, no user input.
		$entries = is_array( $entries ) ? $entries : [];

		$is_enabled  = $this->settings->is_action_log_enabled();
		$clear_nonce = wp_create_nonce( 'counterhand_clear_log' );

		include __DIR__ . '/Admin/views/tab-log.php';
	}
}
