<?php
/**
 * Error state. Shows user-facing copy only — protocol/developer detail goes to
 * the log, never on screen (OAuth guidance: error_uri is for developers).
 *
 * @var array $context {
 *     @type string $headline
 *     @type string $detail
 * }
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<div class="ctrh-card__body ctrh-card__body--centered">
	<span class="ctrh-status-icon ctrh-status-icon--error" aria-hidden="true">
		<svg viewBox="0 0 24 24" width="28" height="28" focusable="false">
			<path d="M12 7v7M12 17.5v.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
		</svg>
	</span>

	<h1 class="ctrh-title"><?php echo esc_html( $context['headline'] ); ?></h1>

	<p class="ctrh-lede"><?php echo esc_html( $context['detail'] ); ?></p>

	<p class="ctrh-hint"><?php esc_html_e( 'Nothing was connected. Try starting the connection again from your AI assistant.', 'counterhand-mcp-for-woocommerce' ); ?></p>

	<div class="ctrh-actions ctrh-actions--single">
		<a class="ctrh-button ctrh-button--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=counterhand-mcp-connect' ) ); ?>">
			<?php esc_html_e( 'Connection help', 'counterhand-mcp-for-woocommerce' ); ?>
		</a>
	</div>
</div>