<?php
/**
 * Connect tab: endpoint URL, verify button, paste-ready client configs.
 *
 * @var string $endpoint_url
 * @var string $fallback_url
 * @var string $verify_nonce
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// No token in any snippet: the client discovers OAuth, opens a browser, and
// the admin approves scopes on the consent screen.
$agmcp_claude_code = sprintf(
	'claude mcp add --transport http woocommerce %s',
	$endpoint_url
);

$agmcp_claude_desktop = wp_json_encode(
	[
		'mcpServers' => [
			'woocommerce' => [
				'type' => 'http',
				'url'  => $endpoint_url,
			],
		],
	],
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);

$agmcp_curl = sprintf(
	"curl -i -X POST %s \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"initialize\",\"params\":{}}'\n# → 401 with a WWW-Authenticate header pointing at the OAuth discovery document",
	$endpoint_url
);
?>
<div class="agmcp-connect">
	<h2><?php esc_html_e( 'Your MCP endpoint', 'agentgate-mcp-for-woocommerce' ); ?></h2>
	<div class="agmcp-endpoint-row">
		<code class="agmcp-endpoint"><?php echo esc_html( $endpoint_url ); ?></code>
		<button type="button" class="button agmcp-copy" data-copy="<?php echo esc_attr( $endpoint_url ); ?>"
			data-copied-label="<?php esc_attr_e( 'Copied!', 'agentgate-mcp-for-woocommerce' ); ?>">
			<?php esc_html_e( 'Copy', 'agentgate-mcp-for-woocommerce' ); ?>
		</button>
	</div>
	<p class="description">
		<?php
		printf(
			/* translators: %s: fallback REST URL */
			esc_html__( 'If your host breaks pretty permalinks, the same endpoint is available at %s.', 'agentgate-mcp-for-woocommerce' ),
			'<code>' . esc_html( $fallback_url ) . '</code>'
		);
		?>
	</p>

	<h2><?php esc_html_e( 'Verify endpoint', 'agentgate-mcp-for-woocommerce' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Confirms the endpoint is reachable and advertising OAuth discovery (a 401 challenge pointing at the authorization server).', 'agentgate-mcp-for-woocommerce' ); ?></p>
	<div class="agmcp-verify-row">
		<button type="button" class="button button-primary" id="agmcp-verify"
			data-nonce="<?php echo esc_attr( $verify_nonce ); ?>"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<?php esc_html_e( 'Verify endpoint', 'agentgate-mcp-for-woocommerce' ); ?>
		</button>
		<span id="agmcp-verify-result" role="status"></span>
	</div>

	<h2><?php esc_html_e( 'Claude Code', 'agentgate-mcp-for-woocommerce' ); ?></h2>
	<div class="agmcp-snippet">
		<pre><code><?php echo esc_html( $agmcp_claude_code ); ?></code></pre>
		<button type="button" class="button agmcp-copy" data-copy="<?php echo esc_attr( $agmcp_claude_code ); ?>"
			data-copied-label="<?php esc_attr_e( 'Copied!', 'agentgate-mcp-for-woocommerce' ); ?>"><?php esc_html_e( 'Copy', 'agentgate-mcp-for-woocommerce' ); ?></button>
	</div>

	<h2><?php esc_html_e( 'Claude Desktop / Cursor (mcp.json)', 'agentgate-mcp-for-woocommerce' ); ?></h2>
	<div class="agmcp-snippet">
		<pre><code><?php echo esc_html( (string) $agmcp_claude_desktop ); ?></code></pre>
		<button type="button" class="button agmcp-copy" data-copy="<?php echo esc_attr( (string) $agmcp_claude_desktop ); ?>"
			data-copied-label="<?php esc_attr_e( 'Copied!', 'agentgate-mcp-for-woocommerce' ); ?>"><?php esc_html_e( 'Copy', 'agentgate-mcp-for-woocommerce' ); ?></button>
	</div>

	<h2><?php esc_html_e( 'Test with curl', 'agentgate-mcp-for-woocommerce' ); ?></h2>
	<div class="agmcp-snippet">
		<pre><code><?php echo esc_html( $agmcp_curl ); ?></code></pre>
		<button type="button" class="button agmcp-copy" data-copy="<?php echo esc_attr( $agmcp_curl ); ?>"
			data-copied-label="<?php esc_attr_e( 'Copied!', 'agentgate-mcp-for-woocommerce' ); ?>"><?php esc_html_e( 'Copy', 'agentgate-mcp-for-woocommerce' ); ?></button>
	</div>

	<p class="description">
		<?php esc_html_e( 'No token to copy: when the assistant connects, your browser opens a consent screen where you approve which scopes it may use. Approved assistants appear on the Connections tab, where you can revoke them.', 'agentgate-mcp-for-woocommerce' ); ?>
	</p>
</div>
