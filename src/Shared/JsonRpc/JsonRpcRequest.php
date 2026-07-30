<?php

declare( strict_types=1 );

namespace Counterhand\Shared\JsonRpc;

defined( 'ABSPATH' ) || exit;

/**
 * A validated JSON-RPC 2.0 request envelope.
 */
final readonly class JsonRpcRequest {

	private function __construct(
		public string $method,
		public array $params,
		public string|int|null $id,
		public bool $is_notification,
	) {}

	/**
	 * @throws JsonRpcEnvelopeException When the body is not a valid JSON-RPC 2.0 request.
	 */
	public static function from_body( string $body ): self {
		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) || json_last_error() !== JSON_ERROR_NONE ) {
			throw new JsonRpcEnvelopeException( JsonRpcErrorCode::ParseError, 'Invalid JSON.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
		}

		if ( array_is_list( $decoded ) ) {
			throw new JsonRpcEnvelopeException( JsonRpcErrorCode::InvalidRequest, 'Batch requests are not supported.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
		}

		if ( ( $decoded['jsonrpc'] ?? null ) !== '2.0' ) {
			throw new JsonRpcEnvelopeException( JsonRpcErrorCode::InvalidRequest, 'Missing or invalid "jsonrpc" version; expected "2.0".' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
		}

		$method = $decoded['method'] ?? null;
		if ( ! is_string( $method ) || '' === $method ) {
			throw new JsonRpcEnvelopeException( JsonRpcErrorCode::InvalidRequest, 'Missing or invalid "method".' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
		}

		$params = $decoded['params'] ?? [];
		if ( ! is_array( $params ) ) {
			throw new JsonRpcEnvelopeException( JsonRpcErrorCode::InvalidRequest, '"params" must be an object or array.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
		}

		$id = $decoded['id'] ?? null;
		if ( null !== $id && ! is_string( $id ) && ! is_int( $id ) ) {
			throw new JsonRpcEnvelopeException( JsonRpcErrorCode::InvalidRequest, '"id" must be a string or number.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
		}

		return new self( $method, $params, $id, ! array_key_exists( 'id', $decoded ) );
	}
}
