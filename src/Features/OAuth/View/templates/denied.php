<?php
/**
 * Denied state. Per OAuth UX convention this is a calm dead-end, not an error:
 * declining is a legitimate choice, so no red, no blame, and a clear way back.
 *
 * @var array $context {
 *     @type string $client_name
 * }
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<div class="counterhand-card__body counterhand-card__body--centered">
	<span class="counterhand-status-icon counterhand-status-icon--neutral" aria-hidden="true">
		<svg viewBox="0 0 24 24" width="28" height="28" focusable="false">
			<path d="M5 12h14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
		</svg>
	</span>

	<h1 class="counterhand-title"><?php esc_html_e( 'Access not granted', 'counterhand-mcp-for-woocommerce' ); ?></h1>

	<p class="counterhand-lede">
		<?php
		printf(
			/* translators: %s: name of the AI application */
			esc_html__( 'You declined the request from %s. Nothing was shared and no connection was created.', 'counterhand-mcp-for-woocommerce' ),
			'<strong>' . esc_html( $context['client_name'] ) . '</strong>'
		);
		?>
	</p>

	<p class="counterhand-hint"><?php esc_html_e( 'You can close this tab. If you meant to allow it, start the connection again from your AI assistant.', 'counterhand-mcp-for-woocommerce' ); ?></p>

	<div class="counterhand-actions counterhand-actions--single">
		<a class="counterhand-button counterhand-button--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=counterhand-mcp-connect&view=connections' ) ); ?>">
			<?php esc_html_e( 'Manage connections', 'counterhand-mcp-for-woocommerce' ); ?>
		</a>
	</div>
</div>