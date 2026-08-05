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
 * this class owns only how MCP spells it.
 */
final readonly class McpServer {

	public const PROTOCOL_VERSION = '2025-06-18';

	private const KNOWN_PROTOCOL_VERSIONS = [ '2025-06-18', '2025-03-26', '2024-11-05' ];

	public function __construct( private ToolDispatcher $dispatcher ) {}

	public function handle( JsonRpcRequest $request, AuthenticatedAgent $agent ): ?JsonRpcResponse {
		if ( str_starts_with( $request->method, 'notifications/' ) ) {
			return null;
		}

		return match ( $request->method ) {
			'initialize' => $this->initialize( $request, $agent ),
			'ping'       => JsonRpcResponse::result( $request->id, [] ),
			'tools/list' => $this->list_tools( $request, $agent ),
			'tools/call' => $this->call_tool( $request, $agent ),
			default      => JsonRpcResponse::error( $request->id, JsonRpcErrorCode::MethodNotFound, sprintf( 'Method "%s" is not supported.', $request->method ) ),
		};
	}

	private function initialize( JsonRpcRequest $request, AuthenticatedAgent $agent ): JsonRpcResponse {
		$requested_version = (string) ( $request->params['protocolVersion'] ?? self::PROTOCOL_VERSION );
		$agreed_version    = in_array( $requested_version, self::KNOWN_PROTOCOL_VERSIONS, true ) ? $requested_version : self::PROTOCOL_VERSION;

		// Group labels, not raw scope values: the model does not need to know
		// this plugin's slug grammar, and a token holding thirty scopes would
		// otherwise spend most of its instructions reciting them.
		$granted = ScopeSummary::of( $agent->scopes() )->labels();

		return JsonRpcResponse::result(
			$request->id,
			[
				'protocolVersion' => $agreed_version,
				'capabilities'    => [ 'tools' => [ 'listChanged' => false ] ],
				'serverInfo'      => [
					'name'    => 'Counterhand MCP for WooCommerce',
					'version' => COUNTERHAND_VERSION,
				],
				'instructions'    => sprintf(
					'WooCommerce store MCP server for %s. Your token can reach: %s. The tools you were given are the whole of what you may do — anything else is out of reach, not merely discouraged. Product creation defaults to draft status. List tools are paginated (per_page, page).',
					get_bloginfo( 'name' ),
					[] !== $granted ? implode( ', ', $granted ) : 'nothing'
				),
			]
		);
	}

	private function list_tools( JsonRpcRequest $request, AuthenticatedAgent $agent ): JsonRpcResponse {
		$tools = array_map(
			static fn ( ToolInterface $tool ): array => [
				'name'        => $tool->name(),
				'description' => $tool->description(),
				'inputSchema' => $tool->input_schema(),
			],
			$this->dispatcher->visible_for( $agent )
		);

		return JsonRpcResponse::result( $request->id, [ 'tools' => $tools ] );
	}

	private function call_tool( JsonRpcRequest $request, AuthenticatedAgent $agent ): JsonRpcResponse {
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
			DispatchStatus::Failed    => JsonRpcResponse::result(
				$request->id,
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
			DispatchStatus::Succeeded => JsonRpcResponse::result(
				$request->id,
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
