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
<div class="counterhand-card__body counterhand-card__body--centered">
	<span class="counterhand-status-icon counterhand-status-icon--success" aria-hidden="true">
		<svg viewBox="0 0 24 24" width="28" height="28" focusable="false">
			<path d="M5 13l4 4L19 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
	</span>

	<h1 class="counterhand-title"><?php esc_html_e( 'Access approved', 'counterhand-mcp-for-woocommerce' ); ?></h1>

	<p class="counterhand-lede">
		<?php
		printf(
			/* translators: %s: name of the AI application */
			esc_html__( '%s may now use your store with the permissions below.', 'counterhand-mcp-for-woocommerce' ),
			'<strong>' . esc_html( $context['client_name'] ) . '</strong>'
		);
		?>
	</p>

	<?php if ( [] !== $context['scope_labels'] ) : ?>
		<ul class="counterhand-granted">
			<?php foreach ( $context['scope_labels'] as $counterhand_label ) : ?>
				<li><?php echo esc_html( $counterhand_label ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<p class="counterhand-hint"><?php esc_html_e( 'Return to your AI assistant to finish connecting. You can close this tab.', 'counterhand-mcp-for-woocommerce' ); ?></p>

	<div class="counterhand-actions counterhand-actions--single">
		<a class="counterhand-button counterhand-button--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=counterhand-mcp-connect&view=connections' ) ); ?>">
			<?php esc_html_e( 'View connections', 'counterhand-mcp-for-woocommerce' ); ?>
		</a>
	</div>
</div>