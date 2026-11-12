<?php
/**
 * Overview card: an invitation to review the plugin on wordpress.org.
 */

declare( strict_types=1 );

use Counterhand\Features\Settings\ReviewInvitation;

defined( 'ABSPATH' ) || exit;
?>
<section class="counterhand-card counterhand-review-invitation" aria-labelledby="counterhand-overview-review">
	<div class="counterhand-card__head">
		<span class="counterhand-card__heading">
			<h2 class="counterhand-card__title" id="counterhand-overview-review"><?php esc_html_e( 'Does Counterhand help your store?', 'counterhand-mcp-for-woocommerce' ); ?></h2>
			<span class="counterhand-card__desc"><?php esc_html_e( 'A review on wordpress.org helps other store owners find it. It takes a minute, and it is what keeps a free plugin going.', 'counterhand-mcp-for-woocommerce' ); ?></span>
		</span>
	</div>
	<div class="counterhand-card__body">
		<form class="counterhand-actions" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( ReviewInvitation::ACTION ); ?>">
			<?php wp_nonce_field( ReviewInvitation::ACTION ); ?>
			<button type="submit" class="button button-primary" name="counterhand_review" value="write" formtarget="_blank"><?php esc_html_e( 'Write a review', 'counterhand-mcp-for-woocommerce' ); ?></button>
			<button type="submit" class="button" name="counterhand_review" value="hide"><?php esc_html_e( 'Don’t show this again', 'counterhand-mcp-for-woocommerce' ); ?></button>
		</form>
	</div>
</section>
