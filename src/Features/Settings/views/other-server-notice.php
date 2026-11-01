<?php
/**
 * Warns that another plugin has started an MCP server this plugin's gates do not cover.
 *
 * @var bool $other_mcp_server
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! $other_mcp_server ) {
	return;
}
?>
<div class="notice notice-warning inline counterhand-other-server">
	<p>
		<strong><?php esc_html_e( 'Another MCP server is running on this store.', 'counterhand-mcp-for-woocommerce' ); ?></strong>
		<?php esc_html_e( 'A plugin such as Rank Math or the MCP Adapter has started its own, under /wp-json/mcp/. It lets a logged-in user reach abilities directly, including WooCommerce\'s own, without the switches, permissions and confirmations below. To turn it off, return false from the mcp_adapter_create_default_server and wpmedia_mcp_oauth_server_enabled filters. While its OAuth server is on, its connector may also not work beside this plugin, because both answer the same discovery address.', 'counterhand-mcp-for-woocommerce' ); ?>
	</p>
</div>
