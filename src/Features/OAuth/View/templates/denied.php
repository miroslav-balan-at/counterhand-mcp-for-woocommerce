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
<div class="agmcp-card__body agmcp-card__body--centered">
	<span class="agmcp-status-icon agmcp-status-icon--neutral" aria-hidden="true">
		<svg viewBox="0 0 24 24" width="28" height="28" focusable="false">
			<path d="M5 12h14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
		</svg>
	</span>

	<h1 class="agmcp-title"><?php esc_html_e( 'Access not granted', 'agentgate-mcp-for-woocommerce' ); ?></h1>

	<p class="agmcp-lede">
		<?php
		printf(
			/* translators: %s: name of the AI application */
			esc_html__( 'You declined the request from %s. Nothing was shared and no connection was created.', 'agentgate-mcp-for-woocommerce' ),
			'<strong>' . esc_html( $context['client_name'] ) . '</strong>'
		);
		?>
	</p>

	<p class="agmcp-hint"><?php esc_html_e( 'You can close this tab. If you meant to allow it, start the connection again from your AI assistant.', 'agentgate-mcp-for-woocommerce' ); ?></p>

	<div class="agmcp-actions agmcp-actions--single">
		<a class="agmcp-button agmcp-button--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=agentgate-mcp&tab=connections' ) ); ?>">
			<?php esc_html_e( 'Manage connections', 'agentgate-mcp-for-woocommerce' ); ?>
		</a>
	</div>
</div>