<?php
/**
 * Connected state — the success page shown when the authorization code has no
 * client callback to return to (loopback client already closed, or a manual
 * flow). Replaces the bare 404 a stray redirect would otherwise land on.
 *
 * @var array $context {
 *     @type string $client_name
 *     @type list<string> $scope_labels
 * }
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<div class="agmcp-card__body agmcp-card__body--centered">
	<span class="agmcp-status-icon agmcp-status-icon--success" aria-hidden="true">
		<svg viewBox="0 0 24 24" width="28" height="28" focusable="false">
			<path d="M5 13l4 4L19 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
	</span>

	<h1 class="agmcp-title"><?php esc_html_e( 'Access approved', 'agentgate-mcp-for-woocommerce' ); ?></h1>

	<p class="agmcp-lede">
		<?php
		printf(
			/* translators: %s: name of the AI application */
			esc_html__( '%s may now use your store with the permissions below.', 'agentgate-mcp-for-woocommerce' ),
			'<strong>' . esc_html( $context['client_name'] ) . '</strong>'
		);
		?>
	</p>

	<?php if ( [] !== $context['scope_labels'] ) : ?>
		<ul class="agmcp-granted">
			<?php foreach ( $context['scope_labels'] as $agmcp_label ) : ?>
				<li><?php echo esc_html( $agmcp_label ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<p class="agmcp-hint"><?php esc_html_e( 'Return to your AI assistant to finish connecting. You can close this tab.', 'agentgate-mcp-for-woocommerce' ); ?></p>

	<div class="agmcp-actions agmcp-actions--single">
		<a class="agmcp-button agmcp-button--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=agentgate-mcp&tab=connections' ) ); ?>">
			<?php esc_html_e( 'View connections', 'agentgate-mcp-for-woocommerce' ); ?>
		</a>
	</div>
</div>