<?php

declare( strict_types=1 );

namespace AgentGateMcp\Shared\JsonRpc;

defined( 'ABSPATH' ) || exit;

/**
 * JSON-RPC 2.0 response payload factories.
 */
final readonly class JsonRpcResponse {

	private function __construct( public array $payload ) {}

	public static function result( string|int|null $id, array $result ): self {
		return new self(
			[
				'jsonrpc' => '2.0',
				'id'      => $id,
				// An empty result must encode as {} per JSON-RPC, not [].
				'result'  => [] === $result ? new \stdClass() : $result,
			]
		);
	}

	public static function error( string|int|null $id, JsonRpcErrorCode $code, string $message, ?array $data = null ): self {
		$error = [
			'code'    => $code->value,
			'message' => $message,
		];

		if ( null !== $data ) {
			$error['data'] = $data;
		}

		return new self(
			[
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => $error,
			]
		);
	}

	public function to_json(): string {
		return (string) wp_json_encode( $this->payload );
	}
}
