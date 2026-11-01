<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Tokens\Authentication\TokenAuthenticator;
use Counterhand\Shared\CanonicalUri;
use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap for the MCP server slice: the pretty /mcp route (primary) and
 * the REST fallback (for hosts with broken rewrites or plain permalinks).
 */
final readonly class McpServerFeature implements FeatureInterface {

	private const QUERY_VAR = 'counterhand_mcp';

	private const REST_FALLBACK_ROUTE = CanonicalUri::REST_NAMESPACE . '/' . CanonicalUri::MCP_PATH;

	private McpEndpoint $endpoint;

	public function __construct(
		private PluginSettings $settings,
		TokenAuthenticator $authenticator,
		McpServer $server,
	) {
		$this->endpoint = new McpEndpoint( $authenticator, $server );
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
		add_filter( ToolDispatcherInterface::SERVES_TOOLS_FILTER, [ $this, 'serves_tools_here' ] );
		add_action( ConfirmationState::PURGE_HOOK, [ new ConfirmationState(), 'purge_expired' ] );

		if ( ! wp_next_scheduled( ConfirmationState::PURGE_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', ConfirmationState::PURGE_HOOK );
		}
	}

	public function serves_tools_here( mixed $serves ): bool {
		$request_uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared against fixed paths, never output.
		$path        = untrailingslashit( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) );
		$rest_route  = (string) ( $_GET['rest_route'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification -- same.

		return true === $serves
			|| untrailingslashit( (string) wp_parse_url( CanonicalUri::mcp(), PHP_URL_PATH ) ) === $path
			|| untrailingslashit( (string) wp_parse_url( home_url( '/' . rest_get_url_prefix() . '/' . self::REST_FALLBACK_ROUTE ), PHP_URL_PATH ) ) === $path
			|| '/' . self::REST_FALLBACK_ROUTE === untrailingslashit( $rest_route );
	}

	public static function register_rewrite(): void {
		add_rewrite_rule( '^' . CanonicalUri::MCP_PATH . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public function add_query_var( array $query_vars ): array {
		$query_vars[] = self::QUERY_VAR;

		return $query_vars;
	}

	public function maybe_handle_pretty_route( \WP $wp ): void {
		if ( '1' !== ( $wp->query_vars[ self::QUERY_VAR ] ?? null ) ) {
			return;
		}

		$reply = $this->endpoint->process(
			new McpHttpRequest(
				(string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared against a fixed verb whitelist, never output.
				(string) file_get_contents( 'php://input' ),
				$this->server_headers()
			)
		);

		status_header( $reply->status );
		header( 'Content-Type: application/json; charset=utf-8' );

		foreach ( $reply->headers as $name => $value ) {
			header( $name . ': ' . $value );
		}

		if ( null !== $reply->body ) {
			echo wp_json_encode( $reply->body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON API response.
		}

		exit;
	}

	public function register_rest_fallback(): void {
		register_rest_route(
			CanonicalUri::REST_NAMESPACE,
			'/' . CanonicalUri::MCP_PATH,
			[
				[
					'methods'             => [ 'POST', 'GET' ],
					'callback'            => [ $this, 'handle_rest_fallback' ],
					// Authentication happens inside process() with a uniform 401;
					// the route itself is open by design.
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	public function handle_rest_fallback( \WP_REST_Request $request ): \WP_REST_Response {
		$headers = [];
		foreach ( $request->get_headers() as $name => $values ) {
			// WP_REST_Request stores header names lower-cased with underscores.
			$headers[ str_replace( '_', '-', (string) $name ) ] = (string) ( is_array( $values ) ? reset( $values ) : $values );
		}

		$reply = $this->endpoint->process( new McpHttpRequest( $request->get_method(), (string) $request->get_body(), $headers, true ) );

		$response = new \WP_REST_Response( $reply->body, $reply->status );

		foreach ( $reply->headers as $name => $value ) {
			$response->header( $name, $value );
		}

		return $response;
	}

	/**
	 * The request's headers from $_SERVER, by name. Raw values: they are compared
	 * and parsed as credentials, never output.
	 *
	 * @return array<string, string>
	 */
	private function server_headers(): array {
		$headers = [];

		foreach ( $_SERVER as $key => $value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- raw header values, never output.
			if ( ! is_string( $value ) || ! str_starts_with( (string) $key, 'HTTP_' ) ) {
				continue;
			}

			$headers[ str_replace( '_', '-', strtolower( substr( (string) $key, 5 ) ) ) ] = $value;
		}

		// Apache CGI/FastCGI setups may expose it only via REDIRECT_.
		if ( ! isset( $headers['authorization'] ) && is_string( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- raw credential, never output.
			$headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- raw credential, never output.
		}

		return $headers;
	}
}
