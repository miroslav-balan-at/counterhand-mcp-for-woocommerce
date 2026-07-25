<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Playground;

use AgentGateMcp\Features\McpServer\McpServer;
use AgentGateMcp\Features\McpServer\ToolRegistry;
use AgentGateMcp\Features\Playground\Provider\ProviderConfig;
use AgentGateMcp\Features\Playground\Provider\ProviderInterface;
use AgentGateMcp\Features\Tokens\Authentication\AuthenticatedAgent;
use AgentGateMcp\Shared\Exception\ToolCallException;
use AgentGateMcp\Shared\JsonRpc\JsonRpcRequest;
use AgentGateMcp\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The agentic loop: ask the model, run any tools it requests through the same
 * MCP dispatch an external assistant would use, feed the results back, repeat
 * until the model stops calling tools.
 */
final readonly class AgentLoop {

	/** Hard stop so a misbehaving model cannot bill indefinitely. */
	private const MAX_ITERATIONS = 12;

	public function __construct(
		private ToolRegistry $tool_registry,
		private McpServer $server,
	) {}

	/**
	 * @param list<array<string,mixed>> $history Provider-format messages from earlier turns.
	 * @return array{messages: list<array<string,mixed>>, transcript: list<array<string,mixed>>, usage: array{input:int,output:int}}
	 */
	public function run(
		ProviderInterface $provider,
		ProviderConfig $config,
		array $history,
		string $user_text,
		AuthenticatedAgent $agent
	): array {
		$messages   = array_merge( $history, [ $provider->user_message( $user_text ) ] );
		$tools      = $this->tool_definitions( $provider, $agent );
		$transcript = [];
		$usage      = [
			'input'  => 0,
			'output' => 0,
		];

		for ( $iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++ ) {
			$turn = $provider->complete( $messages, $tools, $config );

			$usage['input']  += $turn->usage['input'] ?? 0;
			$usage['output'] += $turn->usage['output'] ?? 0;

			if ( '' !== $turn->text ) {
				$transcript[] = [
					'type' => 'text',
					'text' => $turn->text,
				];
			}

			if ( ! $turn->wants_tools || [] === $turn->tool_calls ) {
				return [
					'messages'   => $messages,
					'transcript' => $transcript,
					'usage'      => $usage,
				];
			}

			$messages[] = $provider->assistant_message( $turn );

			$results = [];
			foreach ( $turn->tool_calls as $call ) {
				$outcome = $this->dispatch( $call, $agent );

				$transcript[] = [
					'type'      => 'tool',
					'name'      => $call['name'],
					'arguments' => $call['input'],
					'result'    => $outcome['data'],
					'is_error'  => $outcome['is_error'],
				];

				$results[] = [
					'id'       => $call['id'],
					'name'     => $call['name'],
					'output'   => (string) wp_json_encode( $outcome['data'] ),
					'is_error' => $outcome['is_error'],
				];
			}

			$messages = array_merge( $messages, $provider->tool_result_messages( $results ) );
		}

		$transcript[] = [
			'type' => 'text',
			'text' => __( 'Stopped: the assistant kept calling tools past the safety limit for one message.', 'agentgate-mcp-for-woocommerce' ),
		];

		return [
			'messages'   => $messages,
			'transcript' => $transcript,
			'usage'      => $usage,
		];
	}

	/**
	 * Runs one tool through McpServer so scope gating, schema validation and the
	 * action log behave exactly as they do for an external client.
	 *
	 * @return array{data: mixed, is_error: bool}
	 */
	private function dispatch( array $call, AuthenticatedAgent $agent ): array {
		$envelope = [
			'jsonrpc' => '2.0',
			'id'      => 1,
			'method'  => 'tools/call',
			'params'  => [
				'name'      => $call['name'],
				'arguments' => $call['input'],
			],
		];

		try {
			$response = $this->server->handle(
				JsonRpcRequest::from_body( (string) wp_json_encode( $envelope ) ),
				$agent
			);
		} catch ( \Throwable $throwable ) {
			return [
				'data'     => [ 'error' => $throwable->getMessage() ],
				'is_error' => true,
			];
		}

		$payload = $response?->payload ?? [];

		if ( isset( $payload['error'] ) ) {
			return [
				'data'     => [ 'error' => $payload['error']['message'] ?? 'error' ],
				'is_error' => true,
			];
		}

		$result = $payload['result'] ?? [];

		if ( true === ( $result['isError'] ?? false ) ) {
			return [
				'data'     => [ 'error' => $result['content'][0]['text'] ?? 'error' ],
				'is_error' => true,
			];
		}

		return [
			'data'     => $result['structuredContent'] ?? $result,
			'is_error' => false,
		];
	}

	/** @return list<array<string,mixed>> */
	private function tool_definitions( ProviderInterface $provider, AuthenticatedAgent $agent ): array {
		return array_map(
			static fn ( ToolInterface $tool ): array => $provider->describe_tool(
				$tool->name(),
				$tool->description(),
				$tool->input_schema()
			),
			$this->tool_registry->visible_for( $agent )
		);
	}
}
