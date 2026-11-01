<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Tokens\Authentication\TokenAuthenticator;
use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Shared\CanonicalUri;
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

	/** The revisions that had batches. 2025-06-18 removed them, and earlier clients may send no header. */
	private const BATCHING_REVISIONS = [ '2025-03-26', '2024-11-05' ];

	/** Each member can be a tool call, so one request must not stand in for many unseen. */
	private const MAXIMUM_BATCH_SIZE = 20;

	public function __construct(
		private TokenAuthenticator $authenticator,
		private McpServer $server,
	) {
		$this->gate    = new StatelessGate();
		$this->origins = new OriginPolicy();
	}

	/** RFC 9728 §3.3: the metadata must name the address the client actually used. */
	private static function resource_metadata_url( McpHttpRequest $http ): string {
		return $http->through_rest_fallback
			? rest_url( CanonicalUri::REST_NAMESPACE . '/oauth-protected-resource-fallback' )
			: home_url( '/.well-known/oauth-protected-resource' );
	}

	public function process( McpHttpRequest $http ): McpReply {
		// Before anything else: a rebinding page must learn nothing, not even whether a
		// token would have been accepted. The spec has it ahead of the method check too.
		$origin = $http->header( 'Origin' );
		if ( null !== $origin && ! $this->origins->allows( $origin ) ) {
			return McpReply::json( JsonRpcResponse::error( null, JsonRpcErrorCode::InvalidRequest, 'This origin may not call the server.' ), 403 );
		}

		if ( 'POST' !== strtoupper( $http->method ) ) {
			return new McpReply( 405, null, [ 'Allow' => 'POST' ] );
		}

		try {
			$agent = $this->authenticator->authenticate( $http->header( 'Authorization' ), $http->header( 'X-Counterhand-Token' ) );
		} catch ( AuthenticationFailedException $exception ) {
			// RFC 9728 §5.1: point clients at the protected-resource metadata so they can
			// discover the authorization server and start the OAuth flow. RFC 6750 §3.1: a
			// request that sent no credentials gets no error code.
			$sent_credentials = null !== $http->header( 'Authorization' ) || null !== $http->header( 'X-Counterhand-Token' );

			return new McpReply(
				401,
				[ 'error' => $exception->getMessage() ],
				[
					'WWW-Authenticate' => sprintf(
						'Bearer realm="Counterhand MCP", %sresource_metadata="%s", scope="%s"',
						$sent_credentials ? 'error="invalid_token", ' : '',
						self::resource_metadata_url( $http ),
						implode( ' ', array_map( static fn ( ScopeInterface $scope ): string => $scope->key(), ApiScope::conservative_default() ) )
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

		$batch = JsonRpcRequest::batch_in( $http->body );
		$claim = $http->header( 'MCP-Protocol-Version' );
		if ( null !== $batch && ( null === $claim || in_array( $claim, self::BATCHING_REVISIONS, true ) ) ) {
			return $this->batch( $batch, $agent );
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
				$claimed = $http->header( 'MCP-Protocol-Version' );

				// A version no era of this server knows. Absent is fine: older clients send none.
				if ( null !== $claimed && ! in_array( $claimed, ProtocolVersion::LEGACY, true ) ) {
					return McpReply::json( JsonRpcResponse::error( $request->id, JsonRpcErrorCode::InvalidRequest, 'Unsupported MCP-Protocol-Version header.' ), 400 );
				}

				return $this->legacy( $request, $agent );
			}

			return $this->gate->reject( $request, $http ) ?? $this->server->handle_stateless( $request, $agent );
		} catch ( \Throwable $throwable ) {
			$this->log_internal_error( $throwable );

			return McpReply::json( JsonRpcResponse::error( $request->id, JsonRpcErrorCode::InternalError, 'Internal server error.' ) );
		}
	}

	/** @param list<mixed> $members */
	private function batch( array $members, AuthenticatedAgent $agent ): McpReply {
		if ( [] === $members || count( $members ) > self::MAXIMUM_BATCH_SIZE ) {
			return McpReply::json( JsonRpcResponse::error( null, JsonRpcErrorCode::InvalidRequest, sprintf( 'A batch holds between 1 and %d requests.', self::MAXIMUM_BATCH_SIZE ) ) );
		}

		try {
			$this->authenticator->charge( $agent, count( $members ) - 1 );
		} catch ( RateLimitExceededException $exception ) {
			return new McpReply(
				429,
				[ 'error' => $exception->getMessage() ],
				[ 'Retry-After' => (string) $exception->retry_after_seconds ]
			);
		}

		$responses = [];
		foreach ( $members as $member ) {
			$response = $this->batch_member( $member, $agent );

			if ( null !== $response ) {
				$responses[] = $response->payload;
			}
		}

		return [] === $responses ? McpReply::accepted() : new McpReply( 200, $responses );
	}

	private function batch_member( mixed $member, AuthenticatedAgent $agent ): ?JsonRpcResponse {
		if ( ! is_array( $member ) ) {
			return JsonRpcResponse::error( null, JsonRpcErrorCode::InvalidRequest, 'A batch member must be a request object.' );
		}

		try {
			$request = JsonRpcRequest::from_decoded( $member );
		} catch ( JsonRpcEnvelopeException $exception ) {
			return JsonRpcResponse::error( null, $exception->error_code, $exception->getMessage() );
		}

		// 2025-03-26 lifecycle: initialization comes before anything else, so it stands alone.
		if ( 'initialize' === $request->method ) {
			return JsonRpcResponse::error( $request->id, JsonRpcErrorCode::InvalidRequest, 'initialize must not be part of a batch.' );
		}

		try {
			return $this->server->handle( $request, $agent );
		} catch ( \Throwable $throwable ) {
			$this->log_internal_error( $throwable );

			return JsonRpcResponse::error( $request->id, JsonRpcErrorCode::InternalError, 'Internal server error.' );
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
