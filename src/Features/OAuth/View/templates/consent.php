<?php
/**
 * Consent state: the store admin approves (and may narrow) requested scopes.
 *
 * @var array $context {
 *     @type string                                              $client_name
 *     @type string                                              $client_host
 *     @type \Counterhand\Features\OAuth\View\ConsentScopes      $scopes
 *     @type array<string, string>                               $hidden
 * }
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$ctrh_scopes      = $context['scopes'];
$ctrh_has_write   = $ctrh_scopes->has_write();
$ctrh_client_name = $context['client_name'];
?>
<div class="ctrh-card__body">
	<h1 class="ctrh-title"><?php esc_html_e( 'Authorize AI access', 'counterhand-mcp-for-woocommerce' ); ?></h1>

	<p class="ctrh-lede">
		<?php
		printf(
			/* translators: %s: name of the AI application requesting access */
			esc_html__( '%s wants to connect to your store.', 'counterhand-mcp-for-woocommerce' ),
			'<strong>' . esc_html( $ctrh_client_name ) . '</strong>'
		);
		?>
	</p>

	<?php $ctrh_admin_name = wp_get_current_user()->display_name; ?>
	<div class="ctrh-identity">
		<span class="ctrh-identity__avatar" aria-hidden="true"><?php echo esc_html( mb_substr( $ctrh_admin_name, 0, 1 ) ); ?></span>
		<span class="ctrh-identity__text">
			<span class="ctrh-identity__label"><?php esc_html_e( 'Approving as', 'counterhand-mcp-for-woocommerce' ); ?></span>
			<span class="ctrh-identity__name"><?php echo esc_html( $ctrh_admin_name ); ?></span>
		</span>
	</div>

	<p class="ctrh-origin">
		<span class="ctrh-origin__label"><?php esc_html_e( 'Verified origin', 'counterhand-mcp-for-woocommerce' ); ?></span>
		<code><?php echo esc_html( $context['client_host'] ); ?></code>
	</p>

	<form method="post" class="ctrh-form">
		<?php
		wp_nonce_field( 'ctrh_authorize' );
		foreach ( $context['hidden'] as $ctrh_key => $ctrh_value ) {
			printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $ctrh_key ), esc_attr( $ctrh_value ) );
		}
		?>

		<fieldset class="ctrh-scopes">
			<legend class="ctrh-scopes__legend"><?php esc_html_e( 'Choose what it may do', 'counterhand-mcp-for-woocommerce' ); ?></legend>

			<?php foreach ( $ctrh_scopes->sections as $ctrh_section ) : ?>
				<?php if ( $ctrh_section->is_collapsed() ) : ?>
					<details class="ctrh-scopes__section ctrh-scopes__section--advanced">
						<summary class="ctrh-scopes__heading">
							<?php echo esc_html( $ctrh_section->section->label() ); ?>
							<span class="ctrh-scopes__heading-desc"><?php echo esc_html( $ctrh_section->section->description() ); ?></span>
						</summary>
						<?php require __DIR__ . '/partial-consent-rows.php'; ?>
					</details>
				<?php else : ?>
					<div class="ctrh-scopes__section">
						<p class="ctrh-scopes__heading">
							<?php echo esc_html( $ctrh_section->section->label() ); ?>
							<span class="ctrh-scopes__heading-desc"><?php echo esc_html( $ctrh_section->section->description() ); ?></span>
						</p>
						<?php require __DIR__ . '/partial-consent-rows.php'; ?>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</fieldset>

		<p class="ctrh-hint"><?php esc_html_e( 'Uncheck anything you would rather not grant. You can revoke the whole connection later.', 'counterhand-mcp-for-woocommerce' ); ?></p>

		<?php if ( $ctrh_has_write ) : ?>
			<p class="ctrh-notice ctrh-notice--warning">
				<?php esc_html_e( 'This request includes permission to change store data. New products are always created as drafts for you to review.', 'counterhand-mcp-for-woocommerce' ); ?>
			</p>
		<?php endif; ?>

		<div class="ctrh-actions">
			<button type="submit" name="ctrh_deny" value="1" class="ctrh-button ctrh-button--secondary">
				<?php esc_html_e( 'Deny', 'counterhand-mcp-for-woocommerce' ); ?>
			</button>
			<button type="submit" name="ctrh_approve" value="1" class="ctrh-button ctrh-button--primary">
				<?php esc_html_e( 'Approve access', 'counterhand-mcp-for-woocommerce' ); ?>
			</button>
		</div>
	</form>
</div>

<footer class="ctrh-card__foot">
	<?php esc_html_e( 'You can revoke this connection anytime under WooCommerce → Counterhand MCP.', 'counterhand-mcp-for-woocommerce' ); ?>
</footer>
