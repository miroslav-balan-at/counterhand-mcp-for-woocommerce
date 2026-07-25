<?php
/**
 * Connections tab: read + revoke list of OAuth-granted connections.
 *
 * @var \AgentGateMcp\Features\Tokens\Admin\ConnectionsListTable $list_table
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>

<?php if ( isset( $_GET['agmcp_revoked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'Connection revoked.', 'agentgate-mcp-for-woocommerce' ); ?></p></div>
<?php endif; ?>

<p class="description">
	<?php esc_html_e( 'Each connection is an AI assistant that completed the browser consent flow. Revoking one immediately cuts off its access.', 'agentgate-mcp-for-woocommerce' ); ?>
</p>

<?php $list_table->display(); ?>
