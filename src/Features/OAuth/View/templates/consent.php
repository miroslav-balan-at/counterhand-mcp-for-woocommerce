<?php
/**
 * Consent state: the store admin approves (and may narrow) requested scopes.
 *
 * @var array $context {
 *     @type string                                              $client_name
 *     @type string                                              $client_host
 *     @type \AgentGateMcp\Features\OAuth\View\ConsentScopes      $scopes
 *     @type array<string, string>                               $hidden
 * }
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$agmcp_scopes      = $context['scopes'];
$agmcp_has_write   = $agmcp_scopes->has_write();
$agmcp_client_name = $context['client_name'];
?>
<div class="agmcp-card__body">
	<h1 class="agmcp-title"><?php esc_html_e( 'Authorize AI access', 'agentgate-mcp-for-woocommerce' ); ?></h1>

	<p class="agmcp-lede">
		<?php
		printf(
			/* translators: %s: name of the AI application requesting access */
			esc_html__( '%s wants to connect to your store.', 'agentgate-mcp-for-woocommerce' ),
			'<strong>' . esc_html( $agmcp_client_name ) . '</strong>'
		);
		?>
	</p>

	<?php $agmcp_admin_name = wp_get_current_user()->display_name; ?>
	<div class="agmcp-identity">
		<span class="agmcp-identity__avatar" aria-hidden="true"><?php echo esc_html( mb_substr( $agmcp_admin_name, 0, 1 ) ); ?></span>
		<span class="agmcp-identity__text">
			<span class="agmcp-identity__label"><?php esc_html_e( 'Approving as', 'agentgate-mcp-for-woocommerce' ); ?></span>
			<span class="agmcp-identity__name"><?php echo esc_html( $agmcp_admin_name ); ?></span>
		</span>
	</div>

	<p class="agmcp-origin">
		<span class="agmcp-origin__label"><?php esc_html_e( 'Verified origin', 'agentgate-mcp-for-woocommerce' ); ?></span>
		<code><?php echo esc_html( $context['client_host'] ); ?></code>
	</p>

	<form method="post" class="agmcp-form">
		<?php
		wp_nonce_field( 'agmcp_authorize' );
		foreach ( $context['hidden'] as $agmcp_key => $agmcp_value ) {
			printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $agmcp_key ), esc_attr( $agmcp_value ) );
		}
		?>

		<fieldset class="agmcp-scopes">
			<legend class="agmcp-scopes__legend"><?php esc_html_e( 'Choose what it may do', 'agentgate-mcp-for-woocommerce' ); ?></legend>

			<?php foreach ( $agmcp_scopes->sections as $agmcp_section ) : ?>
				<?php if ( $agmcp_section->is_collapsed() ) : ?>
					<details class="agmcp-scopes__section agmcp-scopes__section--advanced">
						<summary class="agmcp-scopes__heading">
							<?php echo esc_html( $agmcp_section->section->label() ); ?>
							<span class="agmcp-scopes__heading-desc"><?php echo esc_html( $agmcp_section->section->description() ); ?></span>
						</summary>
						<?php require __DIR__ . '/partial-consent-rows.php'; ?>
					</details>
				<?php else : ?>
					<div class="agmcp-scopes__section">
						<p class="agmcp-scopes__heading">
							<?php echo esc_html( $agmcp_section->section->label() ); ?>
							<span class="agmcp-scopes__heading-desc"><?php echo esc_html( $agmcp_section->section->description() ); ?></span>
						</p>
						<?php require __DIR__ . '/partial-consent-rows.php'; ?>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</fieldset>

		<p class="agmcp-hint"><?php esc_html_e( 'Uncheck anything you would rather not grant. You can revoke the whole connection later.', 'agentgate-mcp-for-woocommerce' ); ?></p>

		<?php if ( $agmcp_has_write ) : ?>
			<p class="agmcp-notice agmcp-notice--warning">
				<?php esc_html_e( 'This request includes permission to change store data. New products are always created as drafts for you to review.', 'agentgate-mcp-for-woocommerce' ); ?>
			</p>
		<?php endif; ?>

		<div class="agmcp-actions">
			<button type="submit" name="agmcp_deny" value="1" class="agmcp-button agmcp-button--secondary">
				<?php esc_html_e( 'Deny', 'agentgate-mcp-for-woocommerce' ); ?>
			</button>
			<button type="submit" name="agmcp_approve" value="1" class="agmcp-button agmcp-button--primary">
				<?php esc_html_e( 'Approve access', 'agentgate-mcp-for-woocommerce' ); ?>
			</button>
		</div>
	</form>
</div>

<footer class="agmcp-card__foot">
	<?php esc_html_e( 'You can revoke this connection anytime under WooCommerce → AgentGate MCP.', 'agentgate-mcp-for-woocommerce' ); ?>
</footer>
