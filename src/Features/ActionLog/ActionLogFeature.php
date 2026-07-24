<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\ActionLog;

use AgentGateMcp\Features\ActionLog\Persistence\LogSchema;
use AgentGateMcp\Features\Settings\PluginSettings;
use AgentGateMcp\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Opt-in audit log: subscribes to tool calls, purges by retention, admin tab.
 */
final readonly class ActionLogFeature implements FeatureInterface {

	private const PURGE_HOOK = 'agmcp_purge_log';

	private ActionLogger $logger;

	public function __construct( private PluginSettings $settings ) {
		$this->logger = new ActionLogger();
	}

	public function register(): void {
		add_action( 'admin_init', [ LogSchema::class, 'maybe_upgrade' ] );
		add_action( self::PURGE_HOOK, [ $this, 'purge_expired' ] );
		add_action( 'admin_post_agmcp_clear_log', [ $this, 'handle_clear' ] );

		if ( ! $this->settings->is_action_log_enabled() ) {
			return;
		}

		add_action( 'agmcp_tool_called', [ $this->logger, 'log' ], 10, 4 );

		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::PURGE_HOOK );
		}
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
			wp_die( esc_html__( 'You are not allowed to clear the log.', 'agentgate-mcp-for-woocommerce' ) );
		}

		check_admin_referer( 'agmcp_clear_log' );

		global $wpdb;
		$table_name = LogSchema::table_name();

		$wpdb->query( "TRUNCATE TABLE {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name is plugin-owned, no user input.

		wp_safe_redirect(
			add_query_arg(
				[
					'page'          => 'agentgate-mcp',
					'tab'           => 'log',
					'agmcp_cleared' => '1',
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
		$clear_nonce = wp_create_nonce( 'agmcp_clear_log' );

		include __DIR__ . '/Admin/views/tab-log.php';
	}
}
