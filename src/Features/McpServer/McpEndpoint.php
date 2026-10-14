<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Tokens\Authentication\TokenAuthenticator;
use Counterhand\Shared\Exception\AuthenticationFailedException;
use Counterhand\Shared\Exception\RateLimitExceededException;
use Counterhand\Shared\JsonRpc\JsonRpcEnvelopeException;
use Counterhand\Shared\JsonRpc\JsonRpcErrorCode;
use Counterhand\Shared\JsonRpc\JsonRpcRequest;
use Counterhand\Shared\JsonRpc\JsonRpcResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Transport-agnostic request processing for the /mcp endpoint.
 * Both the pretty rewrite and the REST fallback delegate here.
 *
 * Legacy requests keep HTTP 200 for every JSON-RPC error: a 2025-era client
 * reads a 4xx without a body it recognizes as "no Streamable HTTP server
 * here" and falls back to the old SSE transport. Only stateless requests get
 * the 400s and 404 their revision defines.
 */
final readonly class McpEndpoint {

	private StatelessGate $gate;

	private OriginPolicy $origins;

	public function __construct(
		private TokenAuthenticator $authenticator,
		private McpServer $server,
	) {
		$this->gate    = new StatelessGate();
		$this->origins = new OriginPolicy();
	}

	public function process( McpHttpRequest $http ): McpReply {
		if ( 'POST' !== strtoupper( $http->method ) ) {
			return new McpReply( 405, null, [ 'Allow' => 'POST' ] );
		}

		// Before authentication: a rebinding page must learn nothing, not even
		// whether a token would have been accepted.
		$origin = $http->header( 'Origin' );
		if ( null !== $origin && ! $this->origins->allows( $origin ) ) {
			return McpReply::json( JsonRpcResponse::error( null, JsonRpcErrorCode::InvalidRequest, 'This origin may not call the MCP endpoint.' ), 403 );
		}

		try {
			$agent = $this->authenticator->authenticate( $http->header( 'Authorization' ), $http->header( 'X-Counterhand-Token' ) );
		} catch ( AuthenticationFailedException $exception ) {
			// RFC 9728 §5.1: point clients at the protected-resource metadata so
			// they can discover the authorization server and start the OAuth flow.
			$resource_metadata = home_url( '/.well-known/oauth-protected-resource' );

			return new McpReply(
				401,
				[ 'error' => $exception->getMessage() ],
				[
					'WWW-Authenticate' => sprintf(
						'Bearer realm="Counterhand MCP", error="invalid_token", resource_metadata="%s"',
						$resource_metadata
					),
				]
			);
		} catch ( RateLimitExceededException $exception ) {
			return new McpReply(
				429,
				[ 'error' => $exception->getMessage() ],
				[ 'Retry-After' => (string) $exception->retry_after_seconds ]
			);
		}

		try {
			$request = JsonRpcRequest::from_body( $http->body );
		} catch ( JsonRpcEnvelopeException $exception ) {
			// No body to read the era from; only a modern header earns the 400.
			$header = $http->header( 'MCP-Protocol-Version' );

			return McpReply::json(
				JsonRpcResponse::error( null, $exception->error_code, $exception->getMessage() ),
				null !== $header && ProtocolVersion::is_modern( $header ) ? 400 : 200
			);
		}

		try {
			if ( ! $this->gate->is_stateless( $request, $http ) ) {
				return $this->legacy( $request, $agent );
			}

			return $this->gate->reject( $request, $http ) ?? $this->server->handle_stateless( $request, $agent );
		} catch ( \Throwable $throwable ) {
			$this->log_internal_error( $throwable );

			return McpReply::json( JsonRpcResponse::error( $request->id, JsonRpcErrorCode::InternalError, 'Internal server error.' ) );
		}
	}

	private function legacy( JsonRpcRequest $request, AuthenticatedAgent $agent ): McpReply {
		$response = $this->server->handle( $request, $agent );

		return null === $response ? McpReply::accepted() : McpReply::json( $response );
	}

	private function log_internal_error( \Throwable $throwable ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		wc_get_logger()->error(
			$throwable->getMessage() . "\n" . $throwable->getTraceAsString(),
			[ 'source' => 'counterhand' ]
		);
	}
}
