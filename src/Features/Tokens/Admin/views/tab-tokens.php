<?php
/**
 * Tokens tab: display-once modal, create form, tokens list.
 *
 * @var string|null                                             $new_token    Freshly created token (display once) or null.
 * @var \AgentGateMcp\Features\Tokens\Admin\TokensListTable     $list_table
 * @var list<\AgentGateMcp\Features\Tokens\Domain\ApiScope>     $scopes
 * @var string                                                  $create_nonce
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>

<?php if ( null !== $new_token ) : ?>
	<div class="agmcp-new-token notice notice-success">
		<h3><?php esc_html_e( 'API token created', 'agentgate-mcp-for-woocommerce' ); ?></h3>
		<p><strong><?php esc_html_e( 'Copy it now — it will never be shown again.', 'agentgate-mcp-for-woocommerce' ); ?></strong></p>
		<div class="agmcp-token-reveal">
			<code id="agmcp-token-value"><?php echo esc_html( $new_token ); ?></code>
			<button type="button" class="button" id="agmcp-copy-token" data-copied-label="<?php esc_attr_e( 'Copied!', 'agentgate-mcp-for-woocommerce' ); ?>">
				<?php esc_html_e( 'Copy', 'agentgate-mcp-for-woocommerce' ); ?>
			</button>
		</div>
	</div>
<?php endif; ?>

<?php if ( isset( $_GET['agmcp_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="notice notice-error"><p>
		<?php
		echo 'scope' === sanitize_text_field( wp_unslash( $_GET['agmcp_error'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? esc_html__( 'Select at least one valid scope.', 'agentgate-mcp-for-woocommerce' )
			: esc_html__( 'A label is required.', 'agentgate-mcp-for-woocommerce' );
		?>
	</p></div>
<?php endif; ?>

<div class="agmcp-columns">
	<div class="agmcp-column-create">
		<h2><?php esc_html_e( 'Create token', 'agentgate-mcp-for-woocommerce' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="agmcp_create_token">
			<?php wp_nonce_field( 'agmcp_create_token' ); ?>

			<p>
				<label for="agmcp-label"><strong><?php esc_html_e( 'Label', 'agentgate-mcp-for-woocommerce' ); ?></strong></label><br>
				<input type="text" id="agmcp-label" name="agmcp_label" class="regular-text" required
					placeholder="<?php esc_attr_e( 'e.g. Claude Desktop — Anna', 'agentgate-mcp-for-woocommerce' ); ?>">
			</p>

			<fieldset class="agmcp-scope-grid">
				<legend><strong><?php esc_html_e( 'Scopes', 'agentgate-mcp-for-woocommerce' ); ?></strong></legend>
				<?php foreach ( $scopes as $scope ) : ?>
					<label class="agmcp-scope-option <?php echo $scope->is_write() ? 'agmcp-scope-option--write' : ''; ?>">
						<input type="checkbox" name="agmcp_scopes[]" value="<?php echo esc_attr( $scope->value ); ?>">
						<?php echo esc_html( $scope->label() ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<p>
				<label for="agmcp-expiry"><strong><?php esc_html_e( 'Expires', 'agentgate-mcp-for-woocommerce' ); ?></strong></label><br>
				<select id="agmcp-expiry" name="agmcp_expiry_days">
					<option value="0"><?php esc_html_e( 'Never', 'agentgate-mcp-for-woocommerce' ); ?></option>
					<option value="30"><?php esc_html_e( '30 days', 'agentgate-mcp-for-woocommerce' ); ?></option>
					<option value="90"><?php esc_html_e( '90 days', 'agentgate-mcp-for-woocommerce' ); ?></option>
					<option value="365"><?php esc_html_e( '1 year', 'agentgate-mcp-for-woocommerce' ); ?></option>
				</select>
			</p>

			<?php submit_button( __( 'Create API token', 'agentgate-mcp-for-woocommerce' ) ); ?>
		</form>
	</div>

	<div class="agmcp-column-list">
		<h2><?php esc_html_e( 'Existing tokens', 'agentgate-mcp-for-woocommerce' ); ?></h2>
		<?php $list_table->display(); ?>
	</div>
</div>
