<?php
/**
 * Navigation between the Connect screen's two views.
 *
 * @var \Counterhand\Features\Settings\ConnectTab $active
 * @var array<string, int>                         $counts
 */

declare( strict_types=1 );

use Counterhand\Features\Settings\AdminScreen;
use Counterhand\Features\Settings\ConnectTab;

defined( 'ABSPATH' ) || exit;
?>
<nav class="counterhand-subnav" aria-label="<?php echo esc_attr( AdminScreen::Connect->menu_title() ); ?>">
	<?php foreach ( ConnectTab::cases() as $counterhand_tab ) : ?>
		<a href="<?php echo esc_url( $counterhand_tab->url() ); ?>" class="counterhand-subnav__link"<?php echo $active === $counterhand_tab ? ' aria-current="page"' : ''; ?>>
			<?php echo esc_html( $counterhand_tab->label() ); ?>
			<?php if ( ! empty( $counts[ $counterhand_tab->value ] ) ) : ?>
				<span class="counterhand-subnav__count"><?php echo esc_html( (string) $counts[ $counterhand_tab->value ] ); ?></span>
			<?php endif; ?>
		</a>
	<?php endforeach; ?>
</nav>
