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
<nav class="nav-tab-wrapper counterhand-connect-tabs">
	<?php foreach ( ConnectTab::cases() as $counterhand_tab ) : ?>
		<a href="<?php echo esc_url( $counterhand_tab->url() ); ?>"
			class="nav-tab <?php echo $active === $counterhand_tab ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html( $counterhand_tab->label() ); ?>
			<?php if ( ! empty( $counts[ $counterhand_tab->value ] ) ) : ?>
				<span class="counterhand-tab-count"><?php echo esc_html( (string) $counts[ $counterhand_tab->value ] ); ?></span>
			<?php endif; ?>
		</a>
	<?php endforeach; ?>
</nav>
