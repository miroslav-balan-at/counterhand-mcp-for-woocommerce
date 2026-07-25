<?php
/**
 * Consent state: the store admin approves (and may narrow) requested scopes.
 *
 * @var array $context {
 *     @type string                                             $client_name
 *     @type string                                             $client_host
 *     @type list<\AgentGateMcp\Features\Tokens\Domain\ApiScope> $scopes
 *     @type array<string, string>                              $hidden
 * }
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$agmcp_scopes      = $context['scopes'];
$agmcp_has_write   = (bool) array_filter( $agmcp_scopes, static fn ( $scope ): bool => $scope->is_write() );
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

			<?php foreach ( $agmcp_scopes as $agmcp_scope ) : ?>
				<?php $agmcp_id = 'agmcp-scope-' . sanitize_html_class( $agmcp_scope->value ); ?>
				<label class="agmcp-scope <?php echo $agmcp_scope->is_write() ? 'agmcp-scope--write' : ''; ?>" for="<?php echo esc_attr( $agmcp_id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $agmcp_id ); ?>"
						name="agmcp_scopes[]" value="<?php echo esc_attr( $agmcp_scope->value ); ?>" checked>
					<span class="agmcp-scope__text">
						<span class="agmcp-scope__name"><?php echo esc_html( $agmcp_scope->label() ); ?></span>
						<span class="agmcp-scope__desc"><?php echo esc_html( $agmcp_scope->description() ); ?></span>
					</span>
					<?php if ( $agmcp_scope->is_write() ) : ?>
						<span class="agmcp-tag agmcp-tag--write"><?php esc_html_e( 'Can change data', 'agentgate-mcp-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</label>
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
