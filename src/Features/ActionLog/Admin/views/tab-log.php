<?php
/**
 * Action Log tab: the record of every tool call, newest first, one page at a
 * time. Pagination is core's paginate_links(); this view only lays it out.
 *
 * @var list<array<string, string>> $entries
 * @var bool                        $is_enabled
 * @var int                         $total
 * @var int                         $paged
 * @var int                         $pages
 * @var string                      $pagination
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<div class="counterhand-log">
	<?php if ( ! $is_enabled ) : ?>
		<div class="notice notice-info inline"><p>
			<?php esc_html_e( 'The action log is currently disabled. Enable it on the Settings tab to record tool calls. PII (emails, phone numbers) is masked before storage.', 'counterhand-mcp-for-woocommerce' ); ?>
		</p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['counterhand_cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Log cleared.', 'counterhand-mcp-for-woocommerce' ); ?></p></div>
	<?php endif; ?>

	<section class="counterhand-card">
		<header class="counterhand-card__head counterhand-log__head">
			<div class="counterhand-card__heading">
				<h2 class="counterhand-card__title"><?php esc_html_e( 'Every call, newest first', 'counterhand-mcp-for-woocommerce' ); ?></h2>
				<p class="counterhand-card__desc">
					<?php
					printf(
						/* translators: %s: number of recorded calls. */
						esc_html( _n( '%s recorded call', '%s recorded calls', $total, 'counterhand-mcp-for-woocommerce' ) ),
						esc_html( number_format_i18n( $total ) )
					);
					?>
				</p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="counterhand-log-clear">
				<input type="hidden" name="action" value="counterhand_clear_log">
				<?php wp_nonce_field( 'counterhand_clear_log' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Clear log', 'counterhand-mcp-for-woocommerce' ); ?></button>
			</form>
		</header>

		<div class="counterhand-card__body counterhand-log__body">
			<table class="counterhand-log__table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Time (UTC)', 'counterhand-mcp-for-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Tool', 'counterhand-mcp-for-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Connection', 'counterhand-mcp-for-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Outcome', 'counterhand-mcp-for-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Arguments (PII masked)', 'counterhand-mcp-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( [] === $entries ) : ?>
						<tr>
							<td colspan="5" class="counterhand-log__empty">
								<?php
								echo $is_enabled
									? esc_html__( 'Nothing recorded yet. The next tool call an assistant makes will appear here.', 'counterhand-mcp-for-woocommerce' )
									: esc_html__( 'Nothing recorded. Switch the log on in Settings to start keeping the record.', 'counterhand-mcp-for-woocommerce' );
								?>
							</td>
						</tr>
					<?php endif; ?>
					<?php foreach ( $entries as $counterhand_entry ) : ?>
						<?php $counterhand_failed = 'error' === $counterhand_entry['outcome']; ?>
						<tr>
							<td class="counterhand-log__time"><?php echo esc_html( $counterhand_entry['created_at'] ); ?></td>
							<td><code class="counterhand-log__tool"><?php echo esc_html( $counterhand_entry['tool_name'] ); ?></code></td>
							<td class="counterhand-log__token"><?php echo esc_html( $counterhand_entry['token_label'] ); ?></td>
							<td>
								<span class="counterhand-log__pill <?php echo $counterhand_failed ? 'counterhand-log__pill--fail' : 'counterhand-log__pill--ok'; ?>">
									<?php
									echo $counterhand_failed
										? esc_html__( 'Failed', 'counterhand-mcp-for-woocommerce' )
										: esc_html__( 'Succeeded', 'counterhand-mcp-for-woocommerce' );
									?>
								</span>
							</td>
							<td><code class="counterhand-log-args"><?php echo esc_html( $counterhand_entry['summary'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( '' !== $pagination ) : ?>
			<footer class="counterhand-card__foot counterhand-log__foot">
				<span class="counterhand-log__count">
					<?php
					printf(
						/* translators: 1: current page number, 2: total number of pages. */
						esc_html__( 'Page %1$s of %2$s', 'counterhand-mcp-for-woocommerce' ),
						esc_html( number_format_i18n( $paged ) ),
						esc_html( number_format_i18n( $pages ) )
					);
					?>
				</span>
				<nav class="counterhand-log__pager" aria-label="<?php esc_attr_e( 'Log pages', 'counterhand-mcp-for-woocommerce' ); ?>">
					<?php echo wp_kses_post( $pagination ); ?>
				</nav>
			</footer>
		<?php endif; ?>
	</section>
</div>