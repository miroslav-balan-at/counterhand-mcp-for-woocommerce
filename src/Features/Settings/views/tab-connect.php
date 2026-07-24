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

$agmcp_claude_code = sprintf(
	'claude mcp add --transport http woocommerce %s --header "Authorization: Bearer YOUR_TOKEN"',
	$endpoint_url
);

$agmcp_claude_desktop = wp_json_encode( [
	'mcpServers' => [
		'woocommerce' => [
			'type'    => 'http',
			'url'     => $endpoint_url,
			'headers' => [ 'Authorization' => 'Bearer YOUR_TOKEN' ],
		],
	],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

$agmcp_curl = sprintf(
	"curl -X POST %s \\\n  -H 'Content-Type: application/json' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/list\",\"params\":{}}'",
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

	<h2><?php esc_html_e( 'Verify connection', 'agentgate-mcp-for-woocommerce' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Checks that the endpoint is reachable. Paste a token (optional) for a full MCP handshake test.', 'agentgate-mcp-for-woocommerce' ); ?></p>
	<div class="agmcp-verify-row">
		<input type="password" id="agmcp-verify-token" class="regular-text"
			placeholder="<?php esc_attr_e( 'agmcp_… (optional)', 'agentgate-mcp-for-woocommerce' ); ?>" autocomplete="off">
		<button type="button" class="button button-primary" id="agmcp-verify"
			data-nonce="<?php echo esc_attr( $verify_nonce ); ?>"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<?php esc_html_e( 'Verify', 'agentgate-mcp-for-woocommerce' ); ?>
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
		<?php esc_html_e( 'Replace YOUR_TOKEN with a token from the API Tokens tab. Tokens are shown exactly once at creation.', 'agentgate-mcp-for-woocommerce' ); ?>
	</p>
</div>
