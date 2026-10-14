<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Shared\JsonRpc\JsonRpcResponse;

defined( 'ABSPATH' ) || exit;

/**
 * What the endpoint sends back: an HTTP status, an optional JSON body, headers.
 *
 * The status travels with the reply rather than being derived from an error
 * code, because one code can mean two things: -32602 is an HTTP 400 when the
 * request envelope is malformed and an in-band 200 when a tool is unknown.
 */
final readonly class McpReply {

	/**
	 * @param array<string, mixed>|null $body
	 * @param array<string, string>     $headers
	 */
	public function __construct(
		public int $status,
		public ?array $body = null,
		public array $headers = [],
	) {}

	public static function json( JsonRpcResponse $response, int $status = 200 ): self {
		return new self( $status, $response->payload );
	}

	/** A notification is accepted with no body (Streamable HTTP). */
	public static function accepted(): self {
		return new self( 202 );
	}
}
