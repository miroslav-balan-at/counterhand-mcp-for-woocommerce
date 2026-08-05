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

$counterhand_scopes      = $context['scopes'];
$counterhand_has_write   = $counterhand_scopes->has_write();
$counterhand_client_name = $context['client_name'];
?>
<div class="counterhand-card__body">
	<h1 class="counterhand-title"><?php esc_html_e( 'Authorize AI access', 'counterhand-mcp-for-woocommerce' ); ?></h1>

	<p class="counterhand-lede">
		<?php
		printf(
			/* translators: %s: name of the AI application requesting access */
			esc_html__( '%s wants to connect to your store.', 'counterhand-mcp-for-woocommerce' ),
			'<strong>' . esc_html( $counterhand_client_name ) . '</strong>'
		);
		?>
	</p>

	<?php $counterhand_admin_name = wp_get_current_user()->display_name; ?>
	<div class="counterhand-identity">
		<span class="counterhand-identity__avatar" aria-hidden="true"><?php echo esc_html( mb_substr( $counterhand_admin_name, 0, 1 ) ); ?></span>
		<span class="counterhand-identity__text">
			<span class="counterhand-identity__label"><?php esc_html_e( 'Approving as', 'counterhand-mcp-for-woocommerce' ); ?></span>
			<span class="counterhand-identity__name"><?php echo esc_html( $counterhand_admin_name ); ?></span>
		</span>
	</div>

	<p class="counterhand-origin">
		<span class="counterhand-origin__label"><?php esc_html_e( 'Verified origin', 'counterhand-mcp-for-woocommerce' ); ?></span>
		<code><?php echo esc_html( $context['client_host'] ); ?></code>
	</p>

	<form method="post" class="counterhand-form">
		<?php
		wp_nonce_field( 'counterhand_authorize' );
		foreach ( $context['hidden'] as $counterhand_key => $counterhand_value ) {
			printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $counterhand_key ), esc_attr( $counterhand_value ) );
		}
		?>

		<fieldset class="counterhand-scopes">
			<legend class="counterhand-scopes__legend"><?php esc_html_e( 'Choose what it may do', 'counterhand-mcp-for-woocommerce' ); ?></legend>

			<?php foreach ( $counterhand_scopes->sections as $counterhand_section ) : ?>
				<?php if ( $counterhand_section->is_collapsed() ) : ?>
					<details class="counterhand-scopes__section counterhand-scopes__section--advanced">
						<summary class="counterhand-scopes__heading">
							<?php echo esc_html( $counterhand_section->section->label() ); ?>
							<span class="counterhand-scopes__heading-desc"><?php echo esc_html( $counterhand_section->section->description() ); ?></span>
						</summary>
						<?php require __DIR__ . '/partial-consent-rows.php'; ?>
					</details>
				<?php else : ?>
					<div class="counterhand-scopes__section">
						<p class="counterhand-scopes__heading">
							<?php echo esc_html( $counterhand_section->section->label() ); ?>
							<span class="counterhand-scopes__heading-desc"><?php echo esc_html( $counterhand_section->section->description() ); ?></span>
						</p>
						<?php require __DIR__ . '/partial-consent-rows.php'; ?>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</fieldset>

		<p class="counterhand-hint"><?php esc_html_e( 'Uncheck anything you would rather not grant. You can revoke the whole connection later.', 'counterhand-mcp-for-woocommerce' ); ?></p>

		<?php if ( $counterhand_has_write ) : ?>
			<p class="counterhand-notice counterhand-notice--warning">
				<?php esc_html_e( 'This request includes permission to change store data. New products are always created as drafts for you to review.', 'counterhand-mcp-for-woocommerce' ); ?>
			</p>
		<?php endif; ?>

		<?php
		// Approve first in the DOM so keyboard order matches the stacked mobile
		// layout; CSS puts Deny on the left on wider screens. Reversing this in
		// markup instead would make a keyboard user tab upwards on a phone, on
		// the screen that grants an app access to the store.
		?>
		<div class="counterhand-actions">
			<button type="submit" name="counterhand_approve" value="1" class="counterhand-button counterhand-button--primary">
				<?php esc_html_e( 'Approve access', 'counterhand-mcp-for-woocommerce' ); ?>
			</button>
			<button type="submit" name="counterhand_deny" value="1" class="counterhand-button counterhand-button--secondary">
				<?php esc_html_e( 'Deny', 'counterhand-mcp-for-woocommerce' ); ?>
			</button>
		</div>
	</form>
</div>

<footer class="counterhand-card__foot">
	<?php esc_html_e( 'You can revoke this connection anytime under WooCommerce → Counterhand MCP.', 'counterhand-mcp-for-woocommerce' ); ?>
</footer>
