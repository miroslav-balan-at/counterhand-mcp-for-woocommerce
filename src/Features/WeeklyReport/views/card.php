<?php
/**
 * Dashboard card: one person's weekly report.
 *
 * @var bool       $can_read
 * @var bool       $subscribed
 * @var string     $email
 * @var int|false  $next
 * @var mixed      $notice
 */

declare( strict_types=1 );

use Counterhand\Features\WeeklyReport\WeeklyReportFeature;

defined( 'ABSPATH' ) || exit;
?>
<section class="counterhand-card" aria-labelledby="counterhand-dashboard-weekly-report">
	<div class="counterhand-card__head">
		<span class="counterhand-card__heading">
			<h2 class="counterhand-card__title" id="counterhand-dashboard-weekly-report"><?php esc_html_e( 'Weekly report', 'counterhand-mcp-for-woocommerce' ); ?></h2>
			<span class="counterhand-card__desc"><?php esc_html_e( 'Last week’s sales, best sellers and waiting orders, by email every Monday morning. With a model connected on the Chat screen, a short summary written by AI comes first.', 'counterhand-mcp-for-woocommerce' ); ?></span>
		</span>
	</div>
	<div class="counterhand-card__body">
		<?php if ( is_string( $notice ) ) : ?>
			<p class="counterhand-weekly-report__notice" role="status"><?php echo esc_html( $notice ); ?></p>
		<?php endif; ?>
		<?php if ( ! $can_read ) : ?>
			<p class="counterhand-chip counterhand-chip--warn"><?php esc_html_e( 'Reports are switched off in Settings, so there is nothing to send.', 'counterhand-mcp-for-woocommerce' ); ?></p>
		<?php else : ?>
			<p class="counterhand-stat <?php echo $subscribed ? 'counterhand-stat--on' : 'counterhand-stat--off'; ?>">
				<?php echo $subscribed ? esc_html__( 'On', 'counterhand-mcp-for-woocommerce' ) : esc_html__( 'Off', 'counterhand-mcp-for-woocommerce' ); ?>
			</p>
			<p class="counterhand-dashboard__note">
				<?php
				if ( $subscribed && false !== $next ) {
					printf(
						/* translators: 1: email address, 2: date and time of the next report. */
						esc_html__( 'Sent to %1$s. Next one: %2$s.', 'counterhand-mcp-for-woocommerce' ),
						esc_html( $email ),
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) )
					);
				} elseif ( $subscribed ) {
					/* translators: %s: email address. */
					printf( esc_html__( 'Sent to %s.', 'counterhand-mcp-for-woocommerce' ), esc_html( $email ) );
				} else {
					esc_html_e( 'Only you get it, at your own address. Nobody is signed up for anyone else.', 'counterhand-mcp-for-woocommerce' );
				}
				?>
			</p>
			<div class="counterhand-actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( WeeklyReportFeature::SUBSCRIBE_ACTION ); ?>">
					<input type="hidden" name="counterhand_weekly_report" value="<?php echo $subscribed ? 'off' : 'on'; ?>">
					<?php wp_nonce_field( WeeklyReportFeature::SUBSCRIBE_ACTION ); ?>
					<button type="submit" class="button <?php echo $subscribed ? '' : 'button-primary'; ?>"><?php echo $subscribed ? esc_html__( 'Stop the weekly report', 'counterhand-mcp-for-woocommerce' ) : esc_html__( 'Email me every Monday', 'counterhand-mcp-for-woocommerce' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( WeeklyReportFeature::SEND_ACTION ); ?>">
					<?php wp_nonce_field( WeeklyReportFeature::SEND_ACTION ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'Send last week’s report now', 'counterhand-mcp-for-woocommerce' ); ?></button>
				</form>
			</div>
		<?php endif; ?>
	</div>
</section>
