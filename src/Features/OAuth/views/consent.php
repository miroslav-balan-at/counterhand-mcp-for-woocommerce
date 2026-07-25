<?php
/**
 * OAuth consent page.
 *
 * @var string                                             $store_name
 * @var string                                             $client_name
 * @var list<\AgentGateMcp\Features\Tokens\Domain\ApiScope> $scopes
 * @var array<string, string>                              $hidden
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php esc_html_e( 'Authorize AI access', 'agentgate-mcp-for-woocommerce' ); ?></title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f0f1; margin: 0; color: #1d2327; }
		.agmcp-card { max-width: 460px; margin: 8vh auto; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.13); padding: 32px; }
		.agmcp-card h1 { font-size: 20px; margin: 0 0 8px; }
		.agmcp-client { font-weight: 600; }
		.agmcp-intro { color: #50575e; line-height: 1.5; }
		.agmcp-scopes { list-style: none; padding: 0; margin: 20px 0; border: 1px solid #dcdcde; border-radius: 6px; }
		.agmcp-scope { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-bottom: 1px solid #f0f0f1; }
		.agmcp-scope:last-child { border-bottom: 0; }
		.agmcp-scope--write { background: #fcf6f6; }
		.agmcp-actions { display: flex; gap: 12px; margin-top: 8px; }
		.agmcp-btn { flex: 1; padding: 11px; border-radius: 6px; border: 1px solid transparent; font-size: 14px; font-weight: 600; cursor: pointer; }
		.agmcp-approve { background: #2271b1; color: #fff; }
		.agmcp-deny { background: #f6f7f7; border-color: #c3c4c7; color: #1d2327; }
		.agmcp-foot { margin-top: 18px; font-size: 12px; color: #787c82; text-align: center; }
	</style>
</head>
<body>
	<div class="agmcp-card">
		<h1><?php esc_html_e( 'Authorize AI access', 'agentgate-mcp-for-woocommerce' ); ?></h1>
		<p class="agmcp-intro">
			<?php
			printf(
				/* translators: 1: AI client name, 2: store name */
				esc_html__( '%1$s wants to connect to %2$s. Choose what it may do, then approve.', 'agentgate-mcp-for-woocommerce' ),
				'<span class="agmcp-client">' . esc_html( $client_name ) . '</span>',
				'<strong>' . esc_html( $store_name ) . '</strong>'
			);
			?>
		</p>

		<form method="post">
			<?php
			wp_nonce_field( 'agmcp_authorize' );
			foreach ( $hidden as $agmcp_key => $agmcp_value ) {
				printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $agmcp_key ), esc_attr( $agmcp_value ) );
			}
			?>

			<ul class="agmcp-scopes">
				<?php foreach ( $scopes as $agmcp_scope ) : ?>
					<li class="agmcp-scope <?php echo $agmcp_scope->is_write() ? 'agmcp-scope--write' : ''; ?>">
						<input type="checkbox" id="scope-<?php echo esc_attr( $agmcp_scope->value ); ?>"
							name="agmcp_scopes[]" value="<?php echo esc_attr( $agmcp_scope->value ); ?>" checked>
						<label for="scope-<?php echo esc_attr( $agmcp_scope->value ); ?>"><?php echo esc_html( $agmcp_scope->label() ); ?></label>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="agmcp-actions">
				<button type="submit" name="agmcp_deny" value="1" class="agmcp-btn agmcp-deny"><?php esc_html_e( 'Deny', 'agentgate-mcp-for-woocommerce' ); ?></button>
				<button type="submit" name="agmcp_approve" value="1" class="agmcp-btn agmcp-approve"><?php esc_html_e( 'Approve', 'agentgate-mcp-for-woocommerce' ); ?></button>
			</div>
		</form>

		<p class="agmcp-foot">
			<?php
			printf(
				/* translators: %s: current admin display name */
				esc_html__( 'Signed in as %s. You can revoke this connection anytime under WooCommerce → AgentGate MCP.', 'agentgate-mcp-for-woocommerce' ),
				esc_html( wp_get_current_user()->display_name )
			);
			?>
		</p>
	</div>
</body>
</html>
