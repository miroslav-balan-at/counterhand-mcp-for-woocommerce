<?php
/**
 * Tab nav for the Connect screen.
 *
 * @var \AgentGateMcp\Features\Settings\ConnectTab $active
 * @var array<string, int>                         $counts
 */

declare( strict_types=1 );

use AgentGateMcp\Features\Settings\ConnectTab;

defined( 'ABSPATH' ) || exit;
?>
<nav class="nav-tab-wrapper agmcp-connect-tabs">
	<?php foreach ( ConnectTab::cases() as $agmcp_tab ) : ?>
		<a href="<?php echo esc_url( $agmcp_tab->url() ); ?>"
			class="nav-tab <?php echo $active === $agmcp_tab ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html( $agmcp_tab->label() ); ?>
			<?php if ( ! empty( $counts[ $agmcp_tab->value ] ) ) : ?>
				<span class="agmcp-tab-count"><?php echo esc_html( (string) $counts[ $agmcp_tab->value ] ); ?></span>
			<?php endif; ?>
		</a>
	<?php endforeach; ?>
</nav>
