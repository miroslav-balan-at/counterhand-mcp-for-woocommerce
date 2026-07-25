<?php
/**
 * Connect tab: guided setup per external MCP client.
 *
 * This is the OUTBOUND system — AI apps connecting to this store over OAuth.
 * The Chat tab is separate: it runs a model server-side with your own API key.
 *
 * @var string $endpoint_url
 * @var string $fallback_url
 * @var string $verify_nonce
 */

declare( strict_types=1 );

use AgentGateMcp\Features\Settings\McpClient;

defined( 'ABSPATH' ) || exit;

$agmcp_clients = McpClient::all( $endpoint_url );
?>
<div class="agmcp-connect">
	<p class="description agmcp-connect__intro">
		<?php esc_html_e( 'Connect an outside AI app to this store. Pick your app below and follow the steps — there is no token to copy: the app opens a consent screen in your browser where you approve exactly what it may do.', 'agentgate-mcp-for-woocommerce' ); ?>
	</p>

	<div class="agmcp-endpoint-row">
		<code class="agmcp-endpoint"><?php echo esc_html( $endpoint_url ); ?></code>
		<button type="button" class="button agmcp-copy" data-copy="<?php echo esc_attr( $endpoint_url ); ?>"
			data-copied-label="<?php esc_attr_e( 'Copied!', 'agentgate-mcp-for-woocommerce' ); ?>">
			<?php esc_html_e( 'Copy endpoint', 'agentgate-mcp-for-woocommerce' ); ?>
		</button>
		<button type="button" class="button button-primary" id="agmcp-verify"
			data-nonce="<?php echo esc_attr( $verify_nonce ); ?>"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<?php esc_html_e( 'Verify endpoint', 'agentgate-mcp-for-woocommerce' ); ?>
		</button>
		<span id="agmcp-verify-result" role="status"></span>
	</div>

	<div class="agmcp-clients">
		<?php foreach ( $agmcp_clients as $agmcp_index => $agmcp_client ) : ?>
			<details class="agmcp-client" <?php echo 0 === $agmcp_index ? 'open' : ''; ?>>
				<summary class="agmcp-client__summary">
					<span class="agmcp-client__name"><?php echo esc_html( $agmcp_client->name ); ?></span>
					<span class="agmcp-client__blurb"><?php echo esc_html( $agmcp_client->blurb ); ?></span>
				</summary>

				<div class="agmcp-client__body">
					<ol class="agmcp-client__steps">
						<?php foreach ( $agmcp_client->steps as $agmcp_step ) : ?>
							<li><?php echo esc_html( $agmcp_step ); ?></li>
						<?php endforeach; ?>
					</ol>

					<?php if ( '' !== $agmcp_client->snippet ) : ?>
						<div class="agmcp-snippet">
							<span class="agmcp-snippet__label"><?php echo esc_html( $agmcp_client->snippet_label ); ?></span>
							<pre><code><?php echo esc_html( $agmcp_client->snippet ); ?></code></pre>
							<button type="button" class="button agmcp-copy" data-copy="<?php echo esc_attr( $agmcp_client->snippet ); ?>"
								data-copied-label="<?php esc_attr_e( 'Copied!', 'agentgate-mcp-for-woocommerce' ); ?>">
								<?php esc_html_e( 'Copy', 'agentgate-mcp-for-woocommerce' ); ?>
							</button>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $agmcp_client->docs_url ) : ?>
						<p class="agmcp-client__docs">
							<a href="<?php echo esc_url( $agmcp_client->docs_url ); ?>" target="_blank" rel="noreferrer noopener">
								<?php esc_html_e( 'Official setup documentation', 'agentgate-mcp-for-woocommerce' ); ?> ↗
							</a>
						</p>
					<?php endif; ?>
				</div>
			</details>
		<?php endforeach; ?>
	</div>

	<p class="description">
		<?php
		printf(
			/* translators: %s: fallback REST URL */
			esc_html__( 'If your host breaks pretty permalinks, the same endpoint also answers at %s.', 'agentgate-mcp-for-woocommerce' ),
			'<code>' . esc_html( $fallback_url ) . '</code>'
		);
		?>
		<?php esc_html_e( 'Approved apps appear on the Connections tab, where you can revoke any of them.', 'agentgate-mcp-for-woocommerce' ); ?>
	</p>
</div>
