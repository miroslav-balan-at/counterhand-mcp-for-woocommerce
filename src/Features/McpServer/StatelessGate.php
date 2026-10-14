<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Shared\JsonRpc\JsonRpcErrorCode;
use Counterhand\Shared\JsonRpc\JsonRpcRequest;
use Counterhand\Shared\JsonRpc\JsonRpcResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Tells stateless (2026-07-28) requests from legacy ones, and refuses a
 * stateless request whose envelope or mirrored headers are wrong.
 *
 * The body decides the era, never the header: 2025-era clients already send
 * MCP-Protocol-Version on every request, so a header alone proves nothing. A
 * request is stateless when params._meta claims a modern revision, or when a
 * modern header arrives with no claim at all (a malformed modern request, which
 * reject() answers). Everything else, initialize included, is legacy, and the
 * legacy path stays byte-identical to what 2025-era clients rely on.
 */
final readonly class StatelessGate {

	private const META_VERSION = 'io.modelcontextprotocol/protocolVersion';

	private const META_CAPABILITIES = 'io.modelcontextprotocol/clientCapabilities';

	/** Methods whose target the Mcp-Name header mirrors, and the parameter it mirrors. */
	private const NAMED_TARGET = [
		'tools/call'     => 'name',
		'prompts/get'    => 'name',
		'resources/read' => 'uri',
	];

	private const BASE64_PREFIX = '=?base64?';

	private const BASE64_SUFFIX = '?=';

	public function is_stateless( JsonRpcRequest $request, McpHttpRequest $http ): bool {
		$meta = $request->params['_meta'] ?? null;

		if ( is_array( $meta ) && array_key_exists( self::META_VERSION, $meta ) ) {
			$claim = $meta[ self::META_VERSION ];

			// A malformed claim is still a claim: it is refused, never served as legacy.
			return ! is_string( $claim ) || ProtocolVersion::is_modern( $claim );
		}

		if ( 'initialize' === $request->method ) {
			return false;
		}

		$header = $http->header( 'MCP-Protocol-Version' );

		return null !== $header && ProtocolVersion::is_modern( $header );
	}

	/** Null when the request may be served; otherwise the HTTP 400 that refuses it. */
	public function reject( JsonRpcRequest $request, McpHttpRequest $http ): ?McpReply {
		$meta  = is_array( $request->params['_meta'] ?? null ) ? $request->params['_meta'] : [];
		$claim = $meta[ self::META_VERSION ] ?? null;

		if ( ! is_string( $claim ) || '' === $claim ) {
			return $this->refuse( $request, JsonRpcErrorCode::InvalidParams, 'params._meta must carry "' . self::META_VERSION . '".' );
		}

		// Before the version is judged: a header that disagrees with the body is
		// refused as a mismatch even when the body names a version nobody serves
		// (the order the conformance suite asserts).
		if ( ! $request->is_notification && $http->header( 'MCP-Protocol-Version' ) !== $claim ) {
			return $this->refuse( $request, JsonRpcErrorCode::HeaderMismatch, 'The MCP-Protocol-Version header must match params._meta.' );
		}

		if ( ! in_array( $claim, ProtocolVersion::MODERN, true ) ) {
			return $this->refuse(
				$request,
				JsonRpcErrorCode::UnsupportedProtocolVersion,
				'Unsupported protocol version',
				[
					'supported' => ProtocolVersion::MODERN,
					'requested' => $claim,
				]
			);
		}

		if ( ! is_array( $meta[ self::META_CAPABILITIES ] ?? null ) ) {
			return $this->refuse( $request, JsonRpcErrorCode::InvalidParams, 'params._meta must carry "' . self::META_CAPABILITIES . '".' );
		}

		// Header rules for posted notifications are left undefined by the spec,
		// and no core notification travels over HTTP; only requests are checked.
		if ( $request->is_notification ) {
			return null;
		}

		return $this->target_mismatch( $request, $http );
	}

	/**
	 * Intermediaries may route on these headers while this server acts on the
	 * body; any disagreement between the two is refused (-32020).
	 */
	private function target_mismatch( JsonRpcRequest $request, McpHttpRequest $http ): ?McpReply {
		if ( $http->header( 'Mcp-Method' ) !== $request->method ) {
			return $this->refuse( $request, JsonRpcErrorCode::HeaderMismatch, 'The Mcp-Method header must match the request method.' );
		}

		$target = self::NAMED_TARGET[ $request->method ] ?? null;
		if ( null === $target ) {
			return null;
		}

		$expected = $request->params[ $target ] ?? null;
		$named    = self::decode( $http->header( 'Mcp-Name' ) );
		if ( ! is_string( $expected ) || $named !== $expected ) {
			return $this->refuse( $request, JsonRpcErrorCode::HeaderMismatch, sprintf( 'The Mcp-Name header must match params.%s.', $target ) );
		}

		return null;
	}

	/**
	 * A value that is not plain header-safe ASCII arrives Base64-encoded between
	 * =?base64? and ?=, and must be decoded before it is compared.
	 */
	private static function decode( ?string $value ): ?string {
		if ( null === $value || ! str_starts_with( $value, self::BASE64_PREFIX ) || ! str_ends_with( $value, self::BASE64_SUFFIX ) ) {
			return $value;
		}

		$decoded = base64_decode( substr( $value, strlen( self::BASE64_PREFIX ), -strlen( self::BASE64_SUFFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- MCP header value encoding, not obfuscation.

		return false === $decoded ? null : $decoded;
	}

	/** @param array<string, mixed>|null $data */
	private function refuse( JsonRpcRequest $request, JsonRpcErrorCode $code, string $message, ?array $data = null ): McpReply {
		return McpReply::json( JsonRpcResponse::error( $request->id, $code, $message, $data ), 400 );
	}
}
