<?php
/**
 * Dashboard: whether AI can reach the store, through what, and what it did today.
 *
 * @var \Counterhand\Features\Settings\PluginSettings                 $settings
 * @var bool                                                          $is_enabled
 * @var int                                                           $live_connections
 * @var int                                                           $readable_groups
 * @var list<\Counterhand\Shared\Tool\ToolGroup>                      $writable_groups
 * @var list<\Counterhand\Features\Tokens\Domain\ScopeGroupInterface> $integrations
 * @var \Counterhand\Features\ActionLog\ActivitySummary|null          $activity
 * @var bool                                                          $other_mcp_server
 * @var bool                                                          $can_save
 */

declare( strict_types=1 );

use Counterhand\Features\Settings\AdminScreen;
use Counterhand\Features\Settings\ConnectTab;

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/other-server-notice.php';
?>
<div class="counterhand-dashboard">
	<section class="counterhand-card" aria-labelledby="counterhand-dashboard-connector">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h2 class="counterhand-card__title" id="counterhand-dashboard-connector"><?php esc_html_e( 'Connector', 'counterhand-mcp-for-woocommerce' ); ?></h2>
			</span>
		</div>
		<div class="counterhand-card__body">
			<p class="counterhand-stat <?php echo $is_enabled ? 'counterhand-stat--on' : 'counterhand-stat--off'; ?>">
				<?php echo $is_enabled ? esc_html__( 'On', 'counterhand-mcp-for-woocommerce' ) : esc_html__( 'Off', 'counterhand-mcp-for-woocommerce' ); ?>
			</p>
			<p class="counterhand-dashboard__note">
				<?php
				echo $is_enabled
					? esc_html__( 'Apps you have approved can reach this store.', 'counterhand-mcp-for-woocommerce' )
					: esc_html__( 'No outside AI app can reach this store.', 'counterhand-mcp-for-woocommerce' );
				?>
			</p>
		</div>
		<?php if ( $can_save ) : ?>
			<div class="counterhand-card__foot"><a href="<?php echo esc_url( AdminScreen::Settings->url() ); ?>"><?php esc_html_e( 'Settings', 'counterhand-mcp-for-woocommerce' ); ?></a></div>
		<?php endif; ?>
	</section>

	<section class="counterhand-card" aria-labelledby="counterhand-dashboard-connections">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h2 class="counterhand-card__title" id="counterhand-dashboard-connections"><?php esc_html_e( 'Connected apps', 'counterhand-mcp-for-woocommerce' ); ?></h2>
			</span>
		</div>
		<div class="counterhand-card__body">
			<p class="counterhand-stat"><?php echo esc_html( number_format_i18n( $live_connections ) ); ?></p>
		</div>
		<div class="counterhand-card__foot"><a href="<?php echo esc_url( ConnectTab::Connections->url() ); ?>"><?php esc_html_e( 'Review or revoke them', 'counterhand-mcp-for-woocommerce' ); ?></a></div>
	</section>

	<section class="counterhand-card" aria-labelledby="counterhand-dashboard-exposed">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h2 class="counterhand-card__title" id="counterhand-dashboard-exposed"><?php esc_html_e( 'Areas switched on', 'counterhand-mcp-for-woocommerce' ); ?></h2>
				<span class="counterhand-card__desc"><?php esc_html_e( 'The most any connection can get. Each one holds only what it was approved for.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="counterhand-card__body">
			<p class="counterhand-stat"><?php echo esc_html( number_format_i18n( $readable_groups ) ); ?></p>
			<p class="counterhand-dashboard__note">
				<?php
				if ( [] === $writable_groups ) {
					esc_html_e( 'None can be changed by an AI client.', 'counterhand-mcp-for-woocommerce' );
				} else {
					printf(
						/* translators: %s: comma-separated names of areas of the store, e.g. "Products, Orders". */
						esc_html__( 'Can be changed: %s.', 'counterhand-mcp-for-woocommerce' ),
						esc_html( implode( wp_get_list_item_separator(), array_map( static fn ( $group ): string => $group->label(), $writable_groups ) ) )
					);
				}
				?>
			</p>
		</div>
		<?php if ( $can_save ) : ?>
			<div class="counterhand-card__foot"><a href="<?php echo esc_url( AdminScreen::Settings->url() ); ?>"><?php esc_html_e( 'Choose areas', 'counterhand-mcp-for-woocommerce' ); ?></a></div>
		<?php endif; ?>
	</section>

	<section class="counterhand-card" aria-labelledby="counterhand-dashboard-activity">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h2 class="counterhand-card__title" id="counterhand-dashboard-activity"><?php esc_html_e( 'Tool calls today', 'counterhand-mcp-for-woocommerce' ); ?></h2>
			</span>
		</div>
		<div class="counterhand-card__body">
			<?php if ( null === $activity ) : ?>
				<p class="counterhand-stat counterhand-stat--off"><?php esc_html_e( 'Not recorded', 'counterhand-mcp-for-woocommerce' ); ?></p>
				<p class="counterhand-dashboard__note"><?php esc_html_e( 'The action log is off, so nothing here can say what apps have done.', 'counterhand-mcp-for-woocommerce' ); ?></p>
			<?php else : ?>
				<p class="counterhand-stat"><?php echo esc_html( number_format_i18n( $activity->calls ) ); ?></p>
				<p class="counterhand-dashboard__note">
					<?php
					printf(
						/* translators: %s: number of tool calls that failed. */
						esc_html( _n( '%s failed.', '%s failed.', $activity->failures, 'counterhand-mcp-for-woocommerce' ) ),
						esc_html( number_format_i18n( $activity->failures ) )
					);
					?>
				</p>
				<?php if ( [] !== $activity->recent_failures ) : ?>
					<ul class="counterhand-dashboard__list">
						<?php foreach ( $activity->recent_failures as $counterhand_failure ) : ?>
							<li>
								<code><?php echo esc_html( $counterhand_failure->tool_name ); ?></code>
								<span class="counterhand-muted">
									<?php echo esc_html( $counterhand_failure->connection ); ?>,
									<?php echo esc_html( wp_date( (string) get_option( 'time_format' ), $counterhand_failure->happened_at->getTimestamp() ) ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php if ( null !== $activity ) : ?>
			<div class="counterhand-card__foot"><a href="<?php echo esc_url( AdminScreen::Log->url() ); ?>"><?php esc_html_e( 'Open the action log', 'counterhand-mcp-for-woocommerce' ); ?></a></div>
		<?php elseif ( $can_save ) : ?>
			<div class="counterhand-card__foot"><a href="<?php echo esc_url( AdminScreen::Settings->url() ); ?>"><?php esc_html_e( 'Switch the log on', 'counterhand-mcp-for-woocommerce' ); ?></a></div>
		<?php endif; ?>
	</section>

	<?php if ( [] !== $integrations ) : ?>
		<section class="counterhand-card" aria-labelledby="counterhand-dashboard-integrations">
			<div class="counterhand-card__head">
				<span class="counterhand-card__heading">
					<h2 class="counterhand-card__title" id="counterhand-dashboard-integrations"><?php esc_html_e( 'Other plugins', 'counterhand-mcp-for-woocommerce' ); ?></h2>
				</span>
			</div>
			<div class="counterhand-card__body">
				<ul class="counterhand-dashboard__list">
					<?php foreach ( $integrations as $counterhand_group ) : ?>
						<li>
							<span><?php echo esc_html( $counterhand_group->label() ); ?></span>
							<span class="counterhand-muted">
								<?php
								if ( $settings->is_group_write_enabled( $counterhand_group ) ) {
									esc_html_e( 'Can read and change', 'counterhand-mcp-for-woocommerce' );
								} elseif ( $settings->is_group_read_enabled( $counterhand_group ) ) {
									esc_html_e( 'Can read', 'counterhand-mcp-for-woocommerce' );
								} else {
									esc_html_e( 'Off', 'counterhand-mcp-for-woocommerce' );
								}
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php if ( $can_save ) : ?>
				<div class="counterhand-card__foot"><a href="<?php echo esc_url( AdminScreen::Integrations->url() ); ?>"><?php esc_html_e( 'Choose plugins', 'counterhand-mcp-for-woocommerce' ); ?></a></div>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
