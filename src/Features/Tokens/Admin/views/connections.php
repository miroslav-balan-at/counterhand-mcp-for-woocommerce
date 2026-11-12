<?php
/**
 * Connected apps: read + revoke list of OAuth-granted connections.
 *
 * Shares the Connect screen with the client cards above — a connection is the
 * result of connecting an app, so it belongs beside the thing that made it.
 *
 * @var \Counterhand\Features\Tokens\Admin\ConnectionsListTable $list_table
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<?php if ( isset( $_GET['counterhand_revoked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides whether to show the notice; the revoke itself checked its nonce. ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'Access revoked.', 'counterhand-mcp-for-woocommerce' ); ?></p></div>
<?php endif; ?>

<p class="description">
	<?php esc_html_e( 'Each app here was approved in the browser and reaches only the areas listed. Revoking one cuts off its access at once.', 'counterhand-mcp-for-woocommerce' ); ?>
</p>

<?php $list_table->display(); ?>
