<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One external MCP client and the shortest route to connecting it.
 *
 * This is the *outbound* system: AI apps connecting to the store. Distinct from
 * the Chat tab, which runs a model here with the store owner's own provider.
 *
 * Every client points at the same endpoint and authorises through the same
 * CIMD consent flow, so the differences captured here are only about where the
 * URL gets pasted. Two clients can do better than pasting: Cursor and VS Code
 * publish install URL schemes, so for them this is genuinely one click.
 */
final readonly class McpClient {

	/**
	 * @param list<string> $steps       Click-path the admin follows in that client.
	 * @param string       $install_url Deep link that configures the client outright, if it has one.
	 * @param string       $open_url    The client's own connector settings page, if it has one.
	 * @param list<string> $match_hosts Hosts whose CIMD documents mean "this client is connected".
	 */
	public function __construct(
		public string $id,
		public string $name,
		public string $blurb,
		public ClientGroup $group,
		public array $steps,
		public string $snippet = '',
		public string $snippet_label = '',
		public string $install_url = '',
		public string $install_label = '',
		public string $open_url = '',
		public string $open_label = '',
		public string $docs_url = '',
		public array $match_hosts = [],
	) {}

	/**
	 * The server name this store suggests to clients that want one.
	 *
	 * Derived from the store rather than hardcoded to "woocommerce" so two
	 * stores configured on the same machine do not collide in one mcp.json.
	 */
	public static function server_slug(): string {
		$slug = sanitize_key( (string) get_bloginfo( 'name' ) );
		$slug = substr( $slug, 0, 32 );

		return '' !== $slug ? $slug : 'woocommerce';
	}

	/** @return list<self> */
	public static function all( string $endpoint_url ): array {
		$slug = self::server_slug();

		return [
			new self(
				id: 'claude',
				name: __( 'Claude', 'counterhand-mcp-for-woocommerce' ),
				blurb: __( 'Web, mobile and the desktop app — one connection covers all three, and it shows up in Claude Code too.', 'counterhand-mcp-for-woocommerce' ),
				group: ClientGroup::Cloud,
				steps: [
					__( 'Open Claude, then go to Customize → Connectors and press the + button.', 'counterhand-mcp-for-woocommerce' ),
					__( 'Choose "Add custom connector", give it a name and paste the store URL.', 'counterhand-mcp-for-woocommerce' ),
					__( 'Press Add, then Connect — your browser opens this store\'s consent screen.', 'counterhand-mcp-for-woocommerce' ),
					__( 'Approve the scopes you want to grant. On Team and Enterprise plans an owner has to add the connector first.', 'counterhand-mcp-for-woocommerce' ),
				],
				snippet: $endpoint_url,
				snippet_label: __( 'Server URL', 'counterhand-mcp-for-woocommerce' ),
				open_url: 'https://claude.ai/customize/connectors',
				open_label: __( 'Copy URL & open Claude', 'counterhand-mcp-for-woocommerce' ),
				docs_url: 'https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp',
				match_hosts: [ 'claude.ai', 'anthropic.com', 'claude.com' ],
			),
			new self(
				id: 'chatgpt',
				name: __( 'ChatGPT', 'counterhand-mcp-for-woocommerce' ),
				blurb: __( 'Add the store as a custom connector. Needs a paid plan with developer mode switched on.', 'counterhand-mcp-for-woocommerce' ),
				group: ClientGroup::Cloud,
				steps: [
					__( 'In ChatGPT, open Settings → Apps & Connectors → Advanced and turn on Developer mode.', 'counterhand-mcp-for-woocommerce' ),
					__( 'Back in Connectors, choose "Add custom connector" and paste the store URL.', 'counterhand-mcp-for-woocommerce' ),
					__( 'Connect, then approve the scopes on this store\'s consent screen.', 'counterhand-mcp-for-woocommerce' ),
					__( 'Custom connectors are web only, and are not available on the free plan.', 'counterhand-mcp-for-woocommerce' ),
				],
				snippet: $endpoint_url,
				snippet_label: __( 'Server URL', 'counterhand-mcp-for-woocommerce' ),
				open_url: 'https://chatgpt.com/#settings/Connectors',
				open_label: __( 'Copy URL & open ChatGPT', 'counterhand-mcp-for-woocommerce' ),
				docs_url: 'https://help.openai.com/en/articles/12584461-developer-mode-and-mcp-apps-in-chatgpt',
				match_hosts: [ 'chatgpt.com', 'openai.com' ],
			),
			new self(
				id: 'cursor',
				name: __( 'Cursor', 'counterhand-mcp-for-woocommerce' ),
				blurb: __( 'Use store tools inside the Cursor editor.', 'counterhand-mcp-for-woocommerce' ),
				group: ClientGroup::Local,
				steps: [
					__( 'Press the button below — Cursor opens and asks you to confirm the server.', 'counterhand-mcp-for-woocommerce' ),
					__( 'Confirm, then approve the scopes on this store\'s consent screen.', 'counterhand-mcp-for-woocommerce' ),
					__( 'If your browser blocks the link, paste the JSON below into your mcp.json instead.', 'counterhand-mcp-for-woocommerce' ),
				],
				snippet: (string) wp_json_encode(
					[
						'mcpServers' => [
							$slug => [
								'type' => 'http',
								'url'  => $endpoint_url,
							],
						],
					],
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
				),
				snippet_label: __( 'mcp.json', 'counterhand-mcp-for-woocommerce' ),
				install_url: self::cursor_deeplink( $slug, $endpoint_url ),
				install_label: __( 'Add to Cursor', 'counterhand-mcp-for-woocommerce' ),
				docs_url: 'https://cursor.com/docs/context/mcp',
				match_hosts: [ 'cursor.com', 'cursor.sh' ],
			),
			new self(
				id: 'vscode',
				name: __( 'VS Code', 'counterhand-mcp-for-woocommerce' ),
				blurb: __( 'Use store tools from Copilot Chat in Visual Studio Code.', 'counterhand-mcp-for-woocommerce' ),
				group: ClientGroup::Local,
				steps: [
					__( 'Press the button below — VS Code opens and asks you to confirm the server.', 'counterhand-mcp-for-woocommerce' ),
					__( 'Confirm, then approve the scopes on this store\'s consent screen.', 'counterhand-mcp-for-woocommerce' ),
					__( 'Manage it later with "MCP: List Servers" from the Command Palette.', 'counterhand-mcp-for-woocommerce' ),
				],
				snippet: $endpoint_url,
				snippet_label: __( 'Server URL', 'counterhand-mcp-for-woocommerce' ),
				install_url: self::vscode_deeplink( $slug, $endpoint_url ),
				install_label: __( 'Add to VS Code', 'counterhand-mcp-for-woocommerce' ),
				docs_url: 'https://code.visualstudio.com/api/extension-guides/ai/mcp',
				match_hosts: [ 'vscode.dev', 'github.com' ],
			),
			new self(
				id: 'claude-code',
				name: __( 'Claude Code', 'counterhand-mcp-for-woocommerce' ),
				blurb: __( 'Manage the store from your terminal. Already covered if you connected Claude above.', 'counterhand-mcp-for-woocommerce' ),
				group: ClientGroup::Local,
				steps: [
					__( 'Run the command below in any terminal.', 'counterhand-mcp-for-woocommerce' ),
					__( 'Start Claude Code and run /mcp — it opens the consent screen in your browser.', 'counterhand-mcp-for-woocommerce' ),
					__( 'Approve the scopes you want to grant.', 'counterhand-mcp-for-woocommerce' ),
				],
				// --scope user matters: without it the server is registered only
				// for the directory the command happened to run in.
				snippet: sprintf( 'claude mcp add --transport http --scope user %s %s', $slug, $endpoint_url ),
				snippet_label: __( 'Terminal command', 'counterhand-mcp-for-woocommerce' ),
				docs_url: 'https://code.claude.com/docs/en/mcp',
			),
			new self(
				id: 'other',
				name: __( 'Any other MCP client', 'counterhand-mcp-for-woocommerce' ),
				blurb: __( 'Anything implementing the Model Context Protocol over HTTP.', 'counterhand-mcp-for-woocommerce' ),
				group: ClientGroup::Local,
				steps: [
					__( 'Add the store URL as a streamable HTTP MCP server.', 'counterhand-mcp-for-woocommerce' ),
					__( 'The client discovers authorization automatically (OAuth 2.1 with PKCE).', 'counterhand-mcp-for-woocommerce' ),
					__( 'Approve the scopes on this store\'s consent screen.', 'counterhand-mcp-for-woocommerce' ),
				],
				snippet: $endpoint_url,
				snippet_label: __( 'Server URL', 'counterhand-mcp-for-woocommerce' ),
				docs_url: 'https://modelcontextprotocol.io/docs/develop/connect-remote-servers',
			),
		];
	}

	/**
	 * Cursor's install scheme: base64 JSON in a query parameter.
	 *
	 * Base64 emits + and /, so the result is percent-encoded before it goes
	 * into the URL.
	 */
	private static function cursor_deeplink( string $slug, string $endpoint_url ): string {
		$config = (string) wp_json_encode(
			[
				'type' => 'http',
				'url'  => $endpoint_url,
			],
			JSON_UNESCAPED_SLASHES
		);

		return 'cursor://anysphere.cursor-deeplink/mcp/install?name=' . rawurlencode( $slug )
			. '&config=' . rawurlencode( base64_encode( $config ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Cursor's documented install-link format, not obfuscation.
	}

	/** VS Code's install scheme: percent-encoded JSON, no base64 layer. */
	private static function vscode_deeplink( string $slug, string $endpoint_url ): string {
		$config = (string) wp_json_encode(
			[
				'name' => $slug,
				'type' => 'http',
				'url'  => $endpoint_url,
			],
			JSON_UNESCAPED_SLASHES
		);

		return 'vscode:mcp/install?' . rawurlencode( $config );
	}
}
