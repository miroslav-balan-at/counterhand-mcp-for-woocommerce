<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\McpServer;

use AgentGateMcp\Features\Tokens\Authentication\TokenAuthenticator;
use AgentGateMcp\Shared\Exception\AuthenticationFailedException;
use AgentGateMcp\Shared\Exception\RateLimitExceededException;
use AgentGateMcp\Shared\JsonRpc\JsonRpcEnvelopeException;
use AgentGateMcp\Shared\JsonRpc\JsonRpcErrorCode;
use AgentGateMcp\Shared\JsonRpc\JsonRpcRequest;
use AgentGateMcp\Shared\JsonRpc\JsonRpcResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Transport-agnostic request processing for the /mcp endpoint.
 * Both the pretty rewrite and the REST fallback delegate here.
 */
final readonly class McpEndpoint {

	public function __construct(
		private TokenAuthenticator $authenticator,
		private McpServer $server,
	) {}

	/**
	 * @return array{status: int, body: array|null, headers: array<string, string>}
	 */
	public function process( string $http_method, string $raw_body, ?string $authorization_header, ?string $fallback_header ): array {
		if ( 'POST' !== strtoupper( $http_method ) ) {
			return [
				'status'  => 405,
				'body'    => null,
				'headers' => [ 'Allow' => 'POST' ],
			];
		}

		try {
			$agent = $this->authenticator->authenticate( $authorization_header, $fallback_header );
		} catch ( AuthenticationFailedException $exception ) {
			// RFC 9728 §5.1: point clients at the protected-resource metadata so
			// they can discover the authorization server and start the OAuth flow.
			$resource_metadata = home_url( '/.well-known/oauth-protected-resource' );

			return [
				'status'  => 401,
				'body'    => [ 'error' => $exception->getMessage() ],
				'headers' => [
					'WWW-Authenticate' => sprintf(
						'Bearer realm="AgentGate MCP", error="invalid_token", resource_metadata="%s"',
						$resource_metadata
					),
				],
			];
		} catch ( RateLimitExceededException $exception ) {
			return [
				'status'  => 429,
				'body'    => [ 'error' => $exception->getMessage() ],
				'headers' => [ 'Retry-After' => (string) $exception->retry_after_seconds ],
			];
		}

		try {
			$request = JsonRpcRequest::from_body( $raw_body );
		} catch ( JsonRpcEnvelopeException $exception ) {
			return [
				'status'  => 200,
				'body'    => JsonRpcResponse::error( null, $exception->error_code, $exception->getMessage() )->payload,
				'headers' => [],
			];
		}

		try {
			$response = $this->server->handle( $request, $agent );
		} catch ( \Throwable $throwable ) {
			$this->log_internal_error( $throwable );

			return [
				'status'  => 200,
				'body'    => JsonRpcResponse::error( $request->id, JsonRpcErrorCode::InternalError, 'Internal server error.' )->payload,
				'headers' => [],
			];
		}

		// Notifications get 202 Accepted with no body (MCP streamable HTTP).
		if ( null === $response ) {
			return [
				'status'  => 202,
				'body'    => null,
				'headers' => [],
			];
		}

		return [
			'status'  => 200,
			'body'    => $response->payload,
			'headers' => [],
		];
	}

	private function log_internal_error( \Throwable $throwable ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		wc_get_logger()->error(
			$throwable->getMessage() . "\n" . $throwable->getTraceAsString(),
			[ 'source' => 'agentgate' ]
		);
	}
}
