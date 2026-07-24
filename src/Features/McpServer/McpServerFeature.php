<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\McpServer;

use AgentGateMcp\Features\Settings\PluginSettings;
use AgentGateMcp\Features\Tokens\Authentication\TokenAuthenticator;
use AgentGateMcp\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap for the MCP server slice: the pretty /mcp route (primary) and
 * the REST fallback (for hosts with broken rewrites or plain permalinks).
 */
final readonly class McpServerFeature implements FeatureInterface {

	private const QUERY_VAR = 'agmcp_mcp';

	private McpEndpoint $endpoint;

	public function __construct(
		private PluginSettings $settings,
		TokenAuthenticator $authenticator,
		ToolRegistry $tool_registry,
	) {
		$this->endpoint = new McpEndpoint( $authenticator, new McpServer( $tool_registry ) );
	}

	public function register(): void {
		// Master switch off → no MCP surface exists at all.
		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		add_action( 'init', [ self::class, 'register_rewrite' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_action( 'parse_request', [ $this, 'maybe_handle_pretty_route' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_fallback' ] );
	}

	public static function register_rewrite(): void {
		add_rewrite_rule( '^mcp/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public function add_query_var( array $query_vars ): array {
		$query_vars[] = self::QUERY_VAR;

		return $query_vars;
	}

	public function maybe_handle_pretty_route( \WP $wp ): void {
		if ( '1' !== ( $wp->query_vars[ self::QUERY_VAR ] ?? null ) ) {
			return;
		}

		$result = $this->endpoint->process(
			(string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ),
			(string) file_get_contents( 'php://input' ),
			$this->read_authorization_header(),
			$this->read_header( 'HTTP_X_AGENTGATE_TOKEN' )
		);

		status_header( $result['status'] );
		header( 'Content-Type: application/json; charset=utf-8' );

		foreach ( $result['headers'] as $name => $value ) {
			header( $name . ': ' . $value );
		}

		if ( null !== $result['body'] ) {
			echo wp_json_encode( $result['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON API response.
		}

		exit;
	}

	public function register_rest_fallback(): void {
		register_rest_route( 'agentgate/v1', '/mcp', [
			[
				'methods'             => [ 'POST', 'GET' ],
				'callback'            => [ $this, 'handle_rest_fallback' ],
				// Authentication happens inside process() with a uniform 401;
				// the route itself is open by design.
				'permission_callback' => '__return_true',
			],
		] );
	}

	public function handle_rest_fallback( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->endpoint->process(
			$request->get_method(),
			(string) $request->get_body(),
			$request->get_header( 'authorization' ),
			$request->get_header( 'x-agentgate-token' )
		);

		$response = new \WP_REST_Response( $result['body'], $result['status'] );

		foreach ( $result['headers'] as $name => $value ) {
			$response->header( $name, $value );
		}

		return $response;
	}

	private function read_authorization_header(): ?string {
		// Apache CGI/FastCGI setups may expose it only via REDIRECT_.
		return $this->read_header( 'HTTP_AUTHORIZATION' ) ?? $this->read_header( 'REDIRECT_HTTP_AUTHORIZATION' );
	}

	private function read_header( string $server_key ): ?string {
		$value = $_SERVER[ $server_key ] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- raw header needed for credential parsing, never output.

		return is_string( $value ) && '' !== $value ? $value : null;
	}
}
