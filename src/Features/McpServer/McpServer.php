<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\McpServer;

use AgentGateMcp\Features\Tokens\Authentication\AuthenticatedAgent;
use AgentGateMcp\Shared\Exception\ScopeDeniedException;
use AgentGateMcp\Shared\Exception\ToolCallException;
use AgentGateMcp\Shared\JsonRpc\JsonRpcErrorCode;
use AgentGateMcp\Shared\JsonRpc\JsonRpcRequest;
use AgentGateMcp\Shared\JsonRpc\JsonRpcResponse;
use AgentGateMcp\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The MCP protocol layer: dispatches one JSON-RPC request to a result.
 */
final readonly class McpServer {

	public const PROTOCOL_VERSION = '2025-06-18';

	private const KNOWN_PROTOCOL_VERSIONS = [ '2025-06-18', '2025-03-26', '2024-11-05' ];

	public function __construct( private ToolRegistry $tool_registry ) {}

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

		$scope_values = array_map(
			static fn ( $scope ): string => $scope->value,
			$agent->scopes()->all()
		);

		return JsonRpcResponse::result(
			$request->id,
			[
				'protocolVersion' => $agreed_version,
				'capabilities'    => [ 'tools' => [ 'listChanged' => false ] ],
				'serverInfo'      => [
					'name'    => 'AgentGate MCP for WooCommerce',
					'version' => AGMCP_VERSION,
				],
				'instructions'    => sprintf(
					'WooCommerce store MCP server for %s. Your token grants these scopes: %s. Product creation defaults to draft status. List tools are paginated (per_page, page).',
					get_bloginfo( 'name' ),
					$scope_values ? implode( ', ', $scope_values ) : 'none'
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
			$this->tool_registry->visible_for( $agent )
		);

		return JsonRpcResponse::result( $request->id, [ 'tools' => $tools ] );
	}

	private function call_tool( JsonRpcRequest $request, AuthenticatedAgent $agent ): JsonRpcResponse {
		$tool_name = $request->params['name'] ?? null;
		if ( ! is_string( $tool_name ) || '' === $tool_name ) {
			return JsonRpcResponse::error( $request->id, JsonRpcErrorCode::InvalidParams, 'Missing tool "name".' );
		}

		// Resolving through the agent-filtered set makes disabled or
		// out-of-scope tools indistinguishable from nonexistent ones.
		$tool = $this->tool_registry->resolve_for( $agent, $tool_name );
		if ( null === $tool ) {
			return JsonRpcResponse::error( $request->id, JsonRpcErrorCode::InvalidParams, sprintf( 'Unknown tool "%s".', $tool_name ) );
		}

		$arguments = $request->params['arguments'] ?? [];
		if ( ! is_array( $arguments ) ) {
			return JsonRpcResponse::error( $request->id, JsonRpcErrorCode::InvalidParams, '"arguments" must be an object.' );
		}

		$validated = $this->validate_arguments( $tool, $arguments );
		if ( is_wp_error( $validated ) ) {
			return JsonRpcResponse::error( $request->id, JsonRpcErrorCode::InvalidParams, $validated->get_error_message() );
		}

		try {
			// Defense in depth: the registry already filtered by scope, but the
			// gate inside the dispatch path must hold on its own.
			$agent->require_scope( $tool->required_scope() );

			$data = $tool->execute( $validated );

			/**
			 * Fires after every tool call (success or failure) — the ActionLog
			 * slice subscribes here.
			 *
			 * @param string $tool_name  Tool that ran.
			 * @param string $token_label Label of the calling token.
			 * @param bool   $is_error   Whether the call failed.
			 * @param array  $arguments  Validated tool arguments.
			 */
			do_action( 'agmcp_tool_called', $tool->name(), $agent->token->label, false, $validated );

			return JsonRpcResponse::result(
				$request->id,
				[
					'content'           => [
						[
							'type' => 'text',
							'text' => (string) wp_json_encode( $data ),
						],
					],
					'structuredContent' => $data,
					'isError'           => false,
				]
			);
		} catch ( ScopeDeniedException | ToolCallException $exception ) {
			/** This hook is documented above. */
			do_action( 'agmcp_tool_called', $tool->name(), $agent->token->label, true, $validated );

			// Tool failures are results, not protocol errors — the agent can read
			// the message and self-correct (MCP spec).
			return JsonRpcResponse::result(
				$request->id,
				[
					'content' => [
						[
							'type' => 'text',
							'text' => $exception->getMessage(),
						],
					],
					'isError' => true,
				]
			);
		}
	}

	private function validate_arguments( ToolInterface $tool, array $arguments ): array|\WP_Error {
		$schema = $tool->input_schema();

		// rest_sanitize/validate_value_from_schema do NOT apply defaults —
		// without this, absent args fall through to WooCommerce's own defaults
		// (e.g. create_product would publish instead of draft).
		$arguments = $this->apply_schema_defaults( $schema, $arguments );

		$sanitized = rest_sanitize_value_from_schema( $arguments, $schema, 'arguments' );
		if ( is_wp_error( $sanitized ) ) {
			return $sanitized;
		}

		$valid = rest_validate_value_from_schema( $sanitized, $schema, 'arguments' );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		return (array) $sanitized;
	}

	private function apply_schema_defaults( array $schema, array $arguments ): array {
		$properties = $schema['properties'] ?? [];
		if ( ! is_array( $properties ) ) {
			return $arguments;
		}

		foreach ( $properties as $property_name => $property_schema ) {
			if ( ! array_key_exists( $property_name, $arguments ) && is_array( $property_schema ) && array_key_exists( 'default', $property_schema ) ) {
				$arguments[ $property_name ] = $property_schema['default'];
			}
		}

		return $arguments;
	}
}
