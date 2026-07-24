<?php
/**
 * Page shell: heading + tab navigation. Tab content is included after this.
 *
 * @var string $active_tab
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$agmcp_tabs = [
	'settings' => __( 'Settings', 'agentgate-mcp-for-woocommerce' ),
	'tokens'   => __( 'API Tokens', 'agentgate-mcp-for-woocommerce' ),
	'connect'  => __( 'Connect', 'agentgate-mcp-for-woocommerce' ),
	'log'      => __( 'Action Log', 'agentgate-mcp-for-woocommerce' ),
];
?>
<div class="wrap agmcp-wrap">
	<h1><?php esc_html_e( 'AgentGate MCP for WooCommerce', 'agentgate-mcp-for-woocommerce' ); ?></h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $agmcp_tabs as $agmcp_tab_key => $agmcp_tab_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => \AgentGateMcp\Features\Settings\SettingsFeature::PAGE_SLUG, 'tab' => $agmcp_tab_key ], admin_url( 'admin.php' ) ) ); ?>"
				class="nav-tab <?php echo $active_tab === $agmcp_tab_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $agmcp_tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>
