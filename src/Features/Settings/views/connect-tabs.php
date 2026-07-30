<?php
/**
 * Tab nav for the Connect screen.
 *
 * @var \Counterhand\Features\Settings\ConnectTab $active
 * @var array<string, int>                         $counts
 */

declare( strict_types=1 );

use Counterhand\Features\Settings\ConnectTab;

defined( 'ABSPATH' ) || exit;
?>
<nav class="nav-tab-wrapper ctrh-connect-tabs">
	<?php foreach ( ConnectTab::cases() as $ctrh_tab ) : ?>
		<a href="<?php echo esc_url( $ctrh_tab->url() ); ?>"
			class="nav-tab <?php echo $active === $ctrh_tab ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html( $ctrh_tab->label() ); ?>
			<?php if ( ! empty( $counts[ $ctrh_tab->value ] ) ) : ?>
				<span class="ctrh-tab-count"><?php echo esc_html( (string) $counts[ $ctrh_tab->value ] ); ?></span>
			<?php endif; ?>
		</a>
	<?php endforeach; ?>
</nav>
