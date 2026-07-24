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
<div class="agmcp-log">
	<?php if ( ! $is_enabled ) : ?>
		<div class="notice notice-info inline"><p>
			<?php esc_html_e( 'The action log is currently disabled. Enable it on the Settings tab to record tool calls. PII (emails, phone numbers) is masked before storage.', 'agentgate-mcp-for-woocommerce' ); ?>
		</p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['agmcp_cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Log cleared.', 'agentgate-mcp-for-woocommerce' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="agmcp-log-clear">
		<input type="hidden" name="action" value="agmcp_clear_log">
		<?php wp_nonce_field( 'agmcp_clear_log' ); ?>
		<button type="submit" class="button"><?php esc_html_e( 'Clear log', 'agentgate-mcp-for-woocommerce' ); ?></button>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time (UTC)', 'agentgate-mcp-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Tool', 'agentgate-mcp-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Token', 'agentgate-mcp-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Outcome', 'agentgate-mcp-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Arguments (PII masked)', 'agentgate-mcp-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( [] === $entries ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No entries yet.', 'agentgate-mcp-for-woocommerce' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $entries as $agmcp_entry ) : ?>
				<tr>
					<td><?php echo esc_html( $agmcp_entry['created_at'] ); ?></td>
					<td><code><?php echo esc_html( $agmcp_entry['tool_name'] ); ?></code></td>
					<td><?php echo esc_html( $agmcp_entry['token_label'] ); ?></td>
					<td>
						<span class="<?php echo 'error' === $agmcp_entry['outcome'] ? 'agmcp-status--revoked' : 'agmcp-status--active'; ?>">
							<?php echo esc_html( $agmcp_entry['outcome'] ); ?>
						</span>
					</td>
					<td><code class="agmcp-log-args"><?php echo esc_html( $agmcp_entry['summary'] ); ?></code></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
