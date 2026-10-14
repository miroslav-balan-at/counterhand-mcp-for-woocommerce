<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

/**
 * One HTTP request to the MCP endpoint, as both entry points (the /mcp rewrite
 * and the REST fallback) see it.
 */
final readonly class McpHttpRequest {

	/** @var array<string, string> Header values keyed by lower-cased name. */
	private array $headers;

	/** @param array<string, string> $headers Header values, names in any case. */
	public function __construct(
		public string $method,
		public string $body,
		array $headers,
	) {
		$this->headers = array_change_key_case( $headers, CASE_LOWER );
	}

	/** Header names are case-insensitive (RFC 9110); an empty value counts as absent. */
	public function header( string $name ): ?string {
		$value = $this->headers[ strtolower( $name ) ] ?? null;

		return null !== $value && '' !== $value ? $value : null;
	}
}
