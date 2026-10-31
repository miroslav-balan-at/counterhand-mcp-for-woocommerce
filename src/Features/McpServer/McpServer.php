<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Tokens\Domain\ScopeSummary;
use Counterhand\Shared\JsonRpc\JsonRpcErrorCode;
use Counterhand\Shared\JsonRpc\JsonRpcRequest;
use Counterhand\Shared\JsonRpc\JsonRpcResponse;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The MCP protocol layer: translates one JSON-RPC request into a dispatch and
 * frames the outcome as the wire format. Everything a tool call *means* —
 * defaults, validation, scope gating, the audit hook — lives in ToolDispatcher;
 * this class owns only how MCP spells it, in both of the protocol's eras.
 */
final readonly class McpServer {

	public const PROTOCOL_VERSION = ProtocolVersion::LATEST_LEGACY;

	/**
	 * How long a client may reuse a tool list or discovery result. Short,
	 * because both follow the store's settings; a stale list costs nothing
	 * worse than a refused call, since every call is gated again.
	 */
	private const CACHE_TTL_MILLISECONDS = 60000;

	public function __construct( private ToolDispatcher $dispatcher ) {}

	/** A legacy (initialize-era) request. Its wire shape is what 2025-era clients rely on: keep it unchanged. */
	public function handle( JsonRpcRequest $request, AuthenticatedAgent $agent ): ?JsonRpcResponse {
		if ( str_starts_with( $request->method, 'notifications/' ) ) {
			return null;
		}

		return match ( $request->method ) {
			'initialize' => $this->initialize( $request, $agent ),
			'ping'       => JsonRpcResponse::result( $request->id, [] ),
			'tools/list' => JsonRpcResponse::result( $request->id, $this->tool_list( $agent ) ),
			'tools/call' => $this->call_tool( $request, $agent, static fn ( array $result ): JsonRpcResponse => JsonRpcResponse::result( $request->id, $result ) ),
			default      => JsonRpcResponse::error( $request->id, JsonRpcErrorCode::MethodNotFound, sprintf( 'Method "%s" is not supported.', $request->method ) ),
		};
	}

	/**
	 * A stateless (2026-07-28) request that StatelessGate has already admitted.
	 * Every result says it is complete and who answered it; the lists also say
	 * how long they may be cached, and by whom.
	 */
	public function handle_stateless( JsonRpcRequest $request, AuthenticatedAgent $agent ): McpReply {
		if ( $request->is_notification ) {
			return McpReply::accepted();
		}

		return match ( $request->method ) {
			'server/discover' => McpReply::json( $this->complete( $request, $this->discovery( $agent ), true ) ),
			'tools/list'      => McpReply::json( $this->complete( $request, $this->tool_list( $agent ), true ) ),
			'tools/call'      => McpReply::json( $this->call_tool( $request, $agent, fn ( array $result ): JsonRpcResponse => $this->complete( $request, $result, false ) ) ),
			default           => McpReply::json( JsonRpcResponse::error( $request->id, JsonRpcErrorCode::MethodNotFound, sprintf( 'Method "%s" is not supported.', $request->method ) ), 404 ),
		};
	}

	private function initialize( JsonRpcRequest $request, AuthenticatedAgent $agent ): JsonRpcResponse {
		$requested_version = (string) ( $request->params['protocolVersion'] ?? ProtocolVersion::LATEST_LEGACY );
		$agreed_version    = in_array( $requested_version, ProtocolVersion::LEGACY, true ) ? $requested_version : ProtocolVersion::LATEST_LEGACY;

		return JsonRpcResponse::result(
			$request->id,
			[
				'protocolVersion' => $agreed_version,
				'capabilities'    => self::capabilities(),
				'serverInfo'      => self::server_info(),
				'instructions'    => $this->instructions( $agent ),
			]
		);
	}

	/** @return array<string, mixed> */
	private function discovery( AuthenticatedAgent $agent ): array {
		return [
			// Only stateless revisions: a legacy one is negotiated through initialize, not named here.
			'supportedVersions' => ProtocolVersion::MODERN,
			'capabilities'      => self::capabilities(),
			'instructions'      => $this->instructions( $agent ),
		];
	}

	/** @return array<string, array<string, bool>> */
	private static function capabilities(): array {
		return [ 'tools' => [ 'listChanged' => false ] ];
	}

	/** @return array{name: string, version: string} */
	private static function server_info(): array {
		return [
			'name'    => 'Counterhand MCP for WooCommerce',
			'version' => COUNTERHAND_VERSION,
		];
	}

	/**
	 * Group labels, not raw scope values: the model does not need to know this
	 * plugin's slug grammar, and a token holding thirty scopes would otherwise
	 * spend most of its instructions reciting them.
	 *
	 * The last sentence is for the person, relayed by the model: a missing tool
	 * has exactly two causes and one remedy each, and this is the only channel
	 * that reaches the user at the moment they hit the gap.
	 */
	private function instructions( AuthenticatedAgent $agent ): string {
		$granted = ScopeSummary::of( $agent->scopes() )->live_labels();

		return sprintf(
			'WooCommerce store MCP server for %s. Your token can reach: %s. The tools you were given are the whole of what you may do — anything else is out of reach, not merely discouraged. Product creation defaults to draft status. List tools are paginated (per_page, page). If the user asks for something outside these tools, tell them: the area is either switched off for the store (a store admin can enable it under Counterhand MCP → Settings) or was not granted when this app was connected — either way, reconnect the app afterwards, because a connection only ever holds what it was approved with.',
			get_bloginfo( 'name' ),
			[] !== $granted ? implode( ', ', $granted ) : 'nothing'
		);
	}

	/** @return array{tools: list<array<string, mixed>>} */
	private function tool_list( AuthenticatedAgent $agent ): array {
		return [
			'tools' => array_map(
				fn ( ToolInterface $tool ): array => [
					'name'        => $tool->name(),
					'description' => $tool->description(),
					'inputSchema' => $this->dispatcher->published_schema( $tool ),
					// Every hint is sent, because an omitted one defaults to the
					// unsafe reading (destructive, open-world).
					'annotations' => [
						'readOnlyHint'    => $tool->is_read_only(),
						'destructiveHint' => $tool->is_destructive(),
						'idempotentHint'  => $tool->is_idempotent(),
						'openWorldHint'   => $tool->is_open_world(),
					],
				],
				$this->dispatcher->visible_for( $agent )
			),
		];
	}

	/**
	 * The stateless framing of a successful result. Cacheable results are always
	 * private: what a token sees depends on what it was granted.
	 *
	 * @param array<string, mixed> $result
	 */
	private function complete( JsonRpcRequest $request, array $result, bool $cacheable ): JsonRpcResponse {
		$framed = [
			'resultType' => 'complete',
			...$result,
			'_meta'      => [ 'io.modelcontextprotocol/serverInfo' => self::server_info() ],
		];

		if ( $cacheable ) {
			$framed['ttlMs']      = self::CACHE_TTL_MILLISECONDS;
			$framed['cacheScope'] = 'private';
		}

		return JsonRpcResponse::result( $request->id, $framed );
	}

	/**
	 * @param callable(array<string, mixed>): JsonRpcResponse $frame How this era spells a result;
	 *                                                               protocol errors are the same in both.
	 */
	private function call_tool( JsonRpcRequest $request, AuthenticatedAgent $agent, callable $frame ): JsonRpcResponse {
		$tool_name = $request->params['name'] ?? null;
		if ( ! is_string( $tool_name ) || '' === $tool_name ) {
			return JsonRpcResponse::error( $request->id, JsonRpcErrorCode::InvalidParams, 'Missing tool "name".' );
		}

		$arguments = $request->params['arguments'] ?? [];
		if ( ! is_array( $arguments ) ) {
			return JsonRpcResponse::error( $request->id, JsonRpcErrorCode::InvalidParams, '"arguments" must be an object.' );
		}

		$outcome = $this->dispatcher->dispatch( $tool_name, $arguments, $agent );

		return match ( $outcome->status ) {
			DispatchStatus::Rejected  => JsonRpcResponse::error( $request->id, JsonRpcErrorCode::InvalidParams, $outcome->message ),
			// A failed call comes back as a result carrying isError rather than a
			// protocol error, so the agent can read the message and self-correct
			// (MCP spec).
			DispatchStatus::Failed    => $frame(
				[
					'content' => [
						[
							'type' => 'text',
							'text' => $outcome->message,
						],
					],
					'isError' => true,
				]
			),
			DispatchStatus::Succeeded => $frame(
				[
					'content'           => [
						[
							'type' => 'text',
							'text' => (string) wp_json_encode( $outcome->data ),
						],
					],
					'structuredContent' => $outcome->data,
					'isError'           => false,
				]
			),
		};
	}
}
