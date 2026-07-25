<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One external MCP client and the exact steps to connect it.
 *
 * This is the *outbound* system: AI apps connecting to the store over OAuth.
 * Distinct from the Chat tab, which runs a model server-side with the store
 * owner's own provider key.
 */
final readonly class McpClient {

	/**
	 * @param list<string> $steps  Click-path the admin follows in that client.
	 * @param string       $snippet Optional config/command to copy.
	 */
	public function __construct(
		public string $id,
		public string $name,
		public string $blurb,
		public array $steps,
		public string $snippet = '',
		public string $snippet_label = '',
		public string $docs_url = '',
	) {}

	/** @return list<self> */
	public static function all( string $endpoint_url ): array {
		return [
			new self(
				id: 'claude-desktop',
				name: __( 'Claude Desktop', 'agentgate-mcp-for-woocommerce' ),
				blurb: __( 'Chat with your store from the Claude desktop app.', 'agentgate-mcp-for-woocommerce' ),
				steps: [
					__( 'Open Claude Desktop, then Settings → Connectors.', 'agentgate-mcp-for-woocommerce' ),
					__( 'Click “Add custom connector”.', 'agentgate-mcp-for-woocommerce' ),
					__( 'Paste the endpoint URL below and give it a name, then click Add.', 'agentgate-mcp-for-woocommerce' ),
					__( 'Click Connect — your browser opens this store’s consent screen. Approve the scopes you want to grant.', 'agentgate-mcp-for-woocommerce' ),
				],
				snippet: $endpoint_url,
				snippet_label: __( 'Endpoint URL', 'agentgate-mcp-for-woocommerce' ),
				docs_url: 'https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp',
			),
			new self(
				id: 'claude-web',
				name: __( 'Claude (web & mobile)', 'agentgate-mcp-for-woocommerce' ),
				blurb: __( 'Use your store from claude.ai in the browser or on your phone.', 'agentgate-mcp-for-woocommerce' ),
				steps: [
					__( 'Go to claude.ai → Settings → Connectors.', 'agentgate-mcp-for-woocommerce' ),
					__( 'Click “Add custom connector” and paste the endpoint URL.', 'agentgate-mcp-for-woocommerce' ),
					__( 'Click Connect and approve the consent screen that opens.', 'agentgate-mcp-for-woocommerce' ),
					__( 'Your store must be reachable from the public internet — Claude connects from Anthropic’s servers, not your browser.', 'agentgate-mcp-for-woocommerce' ),
				],
				snippet: $endpoint_url,
				snippet_label: __( 'Endpoint URL', 'agentgate-mcp-for-woocommerce' ),
				docs_url: 'https://support.claude.com/en/articles/11503834-build-custom-connectors-via-remote-mcp-servers',
			),
			new self(
				id: 'claude-code',
				name: __( 'Claude Code', 'agentgate-mcp-for-woocommerce' ),
				blurb: __( 'Manage the store from your terminal.', 'agentgate-mcp-for-woocommerce' ),
				steps: [
					__( 'Run the command below in any terminal.', 'agentgate-mcp-for-woocommerce' ),
					__( 'Start Claude Code and run /mcp — it opens the consent screen in your browser.', 'agentgate-mcp-for-woocommerce' ),
					__( 'Approve the scopes; the connection is then available in every session.', 'agentgate-mcp-for-woocommerce' ),
				],
				snippet: sprintf( 'claude mcp add --transport http woocommerce %s', $endpoint_url ),
				snippet_label: __( 'Terminal command', 'agentgate-mcp-for-woocommerce' ),
			),
			new self(
				id: 'chatgpt',
				name: __( 'ChatGPT', 'agentgate-mcp-for-woocommerce' ),
				blurb: __( 'Add the store as a connector in ChatGPT.', 'agentgate-mcp-for-woocommerce' ),
				steps: [
					__( 'In ChatGPT, open Settings → Connectors (developer mode may be required on your plan).', 'agentgate-mcp-for-woocommerce' ),
					__( 'Choose to add an MCP server and paste the endpoint URL.', 'agentgate-mcp-for-woocommerce' ),
					__( 'Complete the browser consent step to finish the connection.', 'agentgate-mcp-for-woocommerce' ),
					__( 'Connector support varies by ChatGPT plan; a paid plan is generally required.', 'agentgate-mcp-for-woocommerce' ),
				],
				snippet: $endpoint_url,
				snippet_label: __( 'Endpoint URL', 'agentgate-mcp-for-woocommerce' ),
			),
			new self(
				id: 'cursor',
				name: __( 'Cursor', 'agentgate-mcp-for-woocommerce' ),
				blurb: __( 'Use store tools inside the Cursor editor.', 'agentgate-mcp-for-woocommerce' ),
				steps: [
					__( 'Open Cursor → Settings → MCP → Add new MCP server.', 'agentgate-mcp-for-woocommerce' ),
					__( 'Paste the JSON below into your mcp.json.', 'agentgate-mcp-for-woocommerce' ),
					__( 'Reload, then approve the consent screen when Cursor connects.', 'agentgate-mcp-for-woocommerce' ),
				],
				snippet: (string) wp_json_encode(
					[
						'mcpServers' => [
							'woocommerce' => [
								'type' => 'http',
								'url'  => $endpoint_url,
							],
						],
					],
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
				),
				snippet_label: __( 'mcp.json', 'agentgate-mcp-for-woocommerce' ),
			),
			new self(
				id: 'other',
				name: __( 'Any other MCP client', 'agentgate-mcp-for-woocommerce' ),
				blurb: __( 'Anything implementing the Model Context Protocol over HTTP.', 'agentgate-mcp-for-woocommerce' ),
				steps: [
					__( 'Point the client at the endpoint URL below — no token needed.', 'agentgate-mcp-for-woocommerce' ),
					__( 'The client discovers authorization automatically (OAuth 2.1 with PKCE).', 'agentgate-mcp-for-woocommerce' ),
					__( 'Approve the consent screen to grant scopes.', 'agentgate-mcp-for-woocommerce' ),
				],
				snippet: $endpoint_url,
				snippet_label: __( 'Endpoint URL', 'agentgate-mcp-for-woocommerce' ),
				docs_url: 'https://modelcontextprotocol.io/docs/develop/connect-remote-servers',
			),
		];
	}
}
