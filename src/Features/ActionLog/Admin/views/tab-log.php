<?php
/**
 * Action Log tab: latest entries + clear button.
 *
 * @var list<array<string, string>> $entries
 * @var bool                        $is_enabled
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<div class="ctrh-log">
	<?php if ( ! $is_enabled ) : ?>
		<div class="notice notice-info inline"><p>
			<?php esc_html_e( 'The action log is currently disabled. Enable it on the Settings tab to record tool calls. PII (emails, phone numbers) is masked before storage.', 'counterhand-mcp-for-woocommerce' ); ?>
		</p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['ctrh_cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Log cleared.', 'counterhand-mcp-for-woocommerce' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ctrh-log-clear">
		<input type="hidden" name="action" value="ctrh_clear_log">
		<?php wp_nonce_field( 'ctrh_clear_log' ); ?>
		<button type="submit" class="button"><?php esc_html_e( 'Clear log', 'counterhand-mcp-for-woocommerce' ); ?></button>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time (UTC)', 'counterhand-mcp-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Tool', 'counterhand-mcp-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Token', 'counterhand-mcp-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Outcome', 'counterhand-mcp-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Arguments (PII masked)', 'counterhand-mcp-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( [] === $entries ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No entries yet.', 'counterhand-mcp-for-woocommerce' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $entries as $ctrh_entry ) : ?>
				<tr>
					<td><?php echo esc_html( $ctrh_entry['created_at'] ); ?></td>
					<td><code><?php echo esc_html( $ctrh_entry['tool_name'] ); ?></code></td>
					<td><?php echo esc_html( $ctrh_entry['token_label'] ); ?></td>
					<td>
						<span class="<?php echo 'error' === $ctrh_entry['outcome'] ? 'ctrh-status--revoked' : 'ctrh-status--active'; ?>">
							<?php echo esc_html( $ctrh_entry['outcome'] ); ?>
						</span>
					</td>
					<td><code class="ctrh-log-args"><?php echo esc_html( $ctrh_entry['summary'] ); ?></code></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
