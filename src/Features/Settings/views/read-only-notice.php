<?php
/**
 * Says why a settings screen's controls cannot be used.
 *
 * @var bool $can_save
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( $can_save ) {
	return;
}
?>
<div class="notice notice-info inline">
	<p><?php esc_html_e( 'Only a site administrator can change these.', 'counterhand-mcp-for-woocommerce' ); ?></p>
</div>
