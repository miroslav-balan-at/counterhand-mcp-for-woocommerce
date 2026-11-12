<?php
/**
 * Overview: what is still to set up, whether AI can reach the store, through what,
 * and what it did today.
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
 * @var list<\Counterhand\Features\Settings\OverviewCardInterface>    $cards
 * @var list<\Counterhand\Features\Settings\SetupStep>                $setup
 */

declare( strict_types=1 );

use Counterhand\Features\Settings\AdminScreen;
use Counterhand\Features\Settings\ConnectTab;

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/other-server-notice.php';

$counterhand_done = count( array_filter( $setup, static fn ( $step ): bool => $step->done ) );
?>
<?php if ( $counterhand_done < count( $setup ) ) : ?>
	<section class="counterhand-card counterhand-setup" aria-labelledby="counterhand-setup-title">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h2 class="counterhand-card__title" id="counterhand-setup-title"><?php esc_html_e( 'Finish setting up', 'counterhand-mcp-for-woocommerce' ); ?></h2>
				<span class="counterhand-card__desc">
					<?php
					printf(
						/* translators: 1: steps done, 2: all steps. */
						esc_html__( '%1$s of %2$s done. Nothing here changes the shop for customers.', 'counterhand-mcp-for-woocommerce' ),
						esc_html( number_format_i18n( $counterhand_done ) ),
						esc_html( number_format_i18n( count( $setup ) ) )
					);
					?>
				</span>
			</span>
		</div>
		<div class="counterhand-card__body">
			<ol class="counterhand-setup__steps">
				<?php foreach ( $setup as $counterhand_step ) : ?>
					<li class="counterhand-setup__step<?php echo $counterhand_step->done ? ' counterhand-setup__step--done' : ''; ?>">
						<span class="counterhand-setup__mark" aria-hidden="true"></span>
						<span class="counterhand-setup__text">
							<strong><?php echo esc_html( $counterhand_step->title ); ?></strong>
							<span class="counterhand-muted"><?php echo esc_html( $counterhand_step->fact ); ?></span>
						</span>
						<?php if ( $counterhand_step->done ) : ?>
							<span class="counterhand-setup__state"><?php esc_html_e( 'Done', 'counterhand-mcp-for-woocommerce' ); ?></span>
						<?php elseif ( null !== $counterhand_step->action_url ) : ?>
							<a class="button button-compact" href="<?php echo esc_url( $counterhand_step->action_url ); ?>"><?php echo esc_html( $counterhand_step->action_label ); ?></a>
						<?php else : ?>
							<span class="counterhand-muted"><?php esc_html_e( 'An administrator can set this.', 'counterhand-mcp-for-woocommerce' ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</section>
<?php endif; ?>
<div class="counterhand-overview">
	<section class="counterhand-card" aria-labelledby="counterhand-overview-connector">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h2 class="counterhand-card__title" id="counterhand-overview-connector"><?php esc_html_e( 'AI access', 'counterhand-mcp-for-woocommerce' ); ?></h2>
			</span>
		</div>
		<div class="counterhand-card__body">
			<p class="counterhand-stat <?php echo $is_enabled ? 'counterhand-stat--on' : 'counterhand-stat--off'; ?>">
				<?php echo $is_enabled ? esc_html__( 'On', 'counterhand-mcp-for-woocommerce' ) : esc_html__( 'Off', 'counterhand-mcp-for-woocommerce' ); ?>
			</p>
			<p class="counterhand-overview__note">
				<?php
				echo $is_enabled
					? esc_html__( 'Apps you have approved can reach this store.', 'counterhand-mcp-for-woocommerce' )
					: esc_html__( 'No outside AI app can reach this store.', 'counterhand-mcp-for-woocommerce' );
				?>
			</p>
		</div>
		<?php if ( $can_save ) : ?>
			<div class="counterhand-card__foot"><a href="<?php echo esc_url( AdminScreen::Access->url() ); ?>"><?php esc_html_e( 'Change access', 'counterhand-mcp-for-woocommerce' ); ?></a></div>
		<?php endif; ?>
	</section>

	<section class="counterhand-card" aria-labelledby="counterhand-overview-connections">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h2 class="counterhand-card__title" id="counterhand-overview-connections"><?php esc_html_e( 'Apps', 'counterhand-mcp-for-woocommerce' ); ?></h2>
			</span>
		</div>
		<div class="counterhand-card__body">
			<p class="counterhand-stat"><?php echo esc_html( number_format_i18n( $live_connections ) ); ?></p>
		</div>
		<div class="counterhand-card__foot"><a href="<?php echo esc_url( ConnectTab::Connections->url() ); ?>"><?php esc_html_e( 'Review or revoke them', 'counterhand-mcp-for-woocommerce' ); ?></a></div>
	</section>

	<section class="counterhand-card" aria-labelledby="counterhand-overview-exposed">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h2 class="counterhand-card__title" id="counterhand-overview-exposed"><?php esc_html_e( 'Areas AI can read', 'counterhand-mcp-for-woocommerce' ); ?></h2>
				<span class="counterhand-card__desc"><?php esc_html_e( 'The most any app can get. Each app holds only what it was approved for.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="counterhand-card__body">
			<p class="counterhand-stat"><?php echo esc_html( number_format_i18n( $readable_groups ) ); ?></p>
			<p class="counterhand-overview__note">
				<?php
				if ( [] === $writable_groups ) {
					esc_html_e( 'AI can change none of them.', 'counterhand-mcp-for-woocommerce' );
				} else {
					printf(
						/* translators: %s: comma-separated names of areas of the store, e.g. "Products, Orders". */
						esc_html__( 'AI can change: %s.', 'counterhand-mcp-for-woocommerce' ),
						esc_html( implode( wp_get_list_item_separator(), array_map( static fn ( $group ): string => $group->label(), $writable_groups ) ) )
					);
				}
				?>
			</p>
		</div>
		<?php if ( $can_save ) : ?>
			<div class="counterhand-card__foot"><a href="<?php echo esc_url( AdminScreen::Access->url() ); ?>"><?php esc_html_e( 'Choose areas', 'counterhand-mcp-for-woocommerce' ); ?></a></div>
		<?php endif; ?>
	</section>

	<section class="counterhand-card" aria-labelledby="counterhand-overview-activity">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h2 class="counterhand-card__title" id="counterhand-overview-activity"><?php esc_html_e( 'Calls today', 'counterhand-mcp-for-woocommerce' ); ?></h2>
			</span>
		</div>
		<div class="counterhand-card__body">
			<?php if ( null === $activity ) : ?>
				<p class="counterhand-stat counterhand-stat--off"><?php esc_html_e( 'Not recorded', 'counterhand-mcp-for-woocommerce' ); ?></p>
				<p class="counterhand-overview__note"><?php esc_html_e( 'Activity is not recorded, so nothing here can say what apps have done.', 'counterhand-mcp-for-woocommerce' ); ?></p>
			<?php else : ?>
				<p class="counterhand-stat"><?php echo esc_html( number_format_i18n( $activity->calls ) ); ?></p>
				<p class="counterhand-overview__note">
					<?php
					printf(
						/* translators: %s: number of tool calls that failed. */
						esc_html( _n( '%s failed.', '%s failed.', $activity->failures, 'counterhand-mcp-for-woocommerce' ) ),
						esc_html( number_format_i18n( $activity->failures ) )
					);
					?>
				</p>
				<?php if ( [] !== $activity->recent_failures ) : ?>
					<ul class="counterhand-overview__list">
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
			<div class="counterhand-card__foot"><a href="<?php echo esc_url( AdminScreen::Activity->url() ); ?>">
			<?php
				printf(
					/* translators: %s: the name of the plugin's Activity screen. */
					esc_html__( 'Open %s', 'counterhand-mcp-for-woocommerce' ),
					esc_html( AdminScreen::Activity->menu_title() )
				);
			?>
				</a></div>
		<?php elseif ( $can_save ) : ?>
			<div class="counterhand-card__foot"><a href="<?php echo esc_url( AdminScreen::Access->url() ); ?>"><?php esc_html_e( 'Turn on recording', 'counterhand-mcp-for-woocommerce' ); ?></a></div>
		<?php endif; ?>
	</section>

	<?php if ( [] !== $integrations ) : ?>
		<section class="counterhand-card" aria-labelledby="counterhand-overview-integrations">
			<div class="counterhand-card__head">
				<span class="counterhand-card__heading">
					<h2 class="counterhand-card__title" id="counterhand-overview-integrations"><?php esc_html_e( 'Other plugins', 'counterhand-mcp-for-woocommerce' ); ?></h2>
				</span>
			</div>
			<div class="counterhand-card__body">
				<ul class="counterhand-overview__list">
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
				<div class="counterhand-card__foot"><a href="<?php echo esc_url( AdminScreen::other_plugins_url() ); ?>"><?php esc_html_e( 'Choose plugins', 'counterhand-mcp-for-woocommerce' ); ?></a></div>
			<?php endif; ?>
		</section>
	<?php endif; ?>
	<?php
	foreach ( $cards as $counterhand_card ) {
		$counterhand_card->render_card();
	}
	?>
</div>
