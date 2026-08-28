<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Features\McpServer\ToolDispatcherInterface;
use Counterhand\Features\Playground\Provider\ProviderConfig;
use Counterhand\Features\Playground\Provider\ProviderInterface;
use Counterhand\Features\Playground\Provider\TokenUsage;
use Counterhand\Features\Playground\Provider\ToolCall;
use Counterhand\Features\Playground\Provider\ToolResult;
use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The agentic loop: ask the model, run any tools it requests through the same
 * gated dispatch pipeline an external assistant hits, feed the results back,
 * repeat until the model stops calling tools.
 */
final readonly class AgentLoop {

	/** Hard stop so a misbehaving model cannot bill indefinitely. */
	private const MAX_ITERATIONS = 12;

	/**
	 * The ceiling used when a provider declines to name one.
	 *
	 * Selection accuracy falls off past roughly 30–50 eagerly-loaded tools, so a
	 * provider that cannot defer the tail has to be given a smaller surface.
	 */
	public const FALLBACK_TOOL_CEILING = 60;

	public function __construct( private ToolDispatcherInterface $dispatcher ) {}

	/**
	 * @param list<array<string,mixed>> $history Provider-format messages from earlier turns.
	 */
	public function run(
		ProviderInterface $provider,
		ProviderConfig $config,
		array $history,
		string $user_text,
		AuthenticatedAgent $agent
	): AgentLoopResult {
		$messages   = array_merge( $history, [ $provider->user_message( $user_text ) ] );
		$tools      = $this->tool_definitions( $provider, $agent );
		$transcript = [];
		$usage      = new TokenUsage();

		for ( $iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++ ) {
			$turn = $provider->complete( $messages, $tools, $config );

			$usage = $usage->plus( $turn->usage );

			if ( '' !== $turn->text ) {
				$transcript[] = [
					'type' => 'text',
					'text' => $turn->text,
				];
			}

			if ( ! $turn->wants_tools || [] === $turn->tool_calls ) {
				return new AgentLoopResult( $messages, $transcript, $usage );
			}

			$messages[] = $provider->assistant_message( $turn );

			$results = [];
			foreach ( $turn->tool_calls as $call ) {
				$data = $this->tool_result( $call, $agent );

				$transcript[] = [
					'type'      => 'tool',
					'name'      => $call->name,
					'arguments' => $call->input,
					'result'    => $data['data'],
					'is_error'  => $data['is_error'],
				];

				$results[] = new ToolResult(
					id: $call->id,
					name: $call->name,
					output: (string) wp_json_encode( $data['data'] ),
					is_error: $data['is_error'],
				);
			}

			$messages = array_merge( $messages, $provider->tool_result_messages( $results ) );
		}

		$transcript[] = [
			'type' => 'text',
			'text' => __( 'Stopped: the assistant kept calling tools past the safety limit for one message.', 'counterhand-mcp-for-woocommerce' ),
		];

		return new AgentLoopResult( $messages, $transcript, $usage );
	}

	/**
	 * Runs one tool through the shared dispatch pipeline, so scope gating,
	 * schema validation and the action log behave exactly as they do for an
	 * external client — with no MCP envelope in between.
	 *
	 * @return array{data: mixed, is_error: bool}
	 */
	private function tool_result( ToolCall $call, AuthenticatedAgent $agent ): array {
		$outcome = $this->dispatcher->dispatch( $call->name, $call->input, $agent );

		return [
			'data'     => $outcome->is_error() ? [ 'error' => $outcome->message ] : $outcome->data,
			'is_error' => $outcome->is_error(),
		];
	}

	/**
	 * Built once per run() and reused across iterations: the visible set cannot
	 * change mid-conversation, and input_schema() asks WooCommerce for its route
	 * args, which is not work to repeat twelve times.
	 *
	 * @throws ToolCallException When more tools are enabled than one request should carry.
	 * @return list<array<string,mixed>>
	 */
	private function tool_definitions( ProviderInterface $provider, AuthenticatedAgent $agent ): array {
		$tools   = $this->dispatcher->visible_for( $agent );
		$ceiling = $provider->max_eager_tools() ?? PHP_INT_MAX;

		if ( count( $tools ) > $ceiling ) {
			throw new ToolCallException(
				sprintf(
					/* translators: 1: number of tools chat can reach, 2: the supported maximum, 3: how many to remove. */
					esc_html__( 'Chat can reach %1$d tools, which is more than this model can carry in one message (%2$d). Open "available to chat" below and untick areas until at least %3$d fewer tools are selected — the areas you untick stay available to your other AI apps. Connecting an Anthropic model instead removes the limit, because Claude can search the tools it needs.', 'counterhand-mcp-for-woocommerce' ),
					count( $tools ),
					(int) $ceiling,
					count( $tools ) - $ceiling
				)
			);
		}

		$definitions = array_map(
			static fn ( ToolInterface $tool ): array => $provider->describe_tool(
				$tool->name(),
				$tool->description(),
				$tool->input_schema()
			),
			$tools
		);

		// Past the point where eager loading hurts selection accuracy, a
		// provider that can defer the tail publishes a searchable catalogue
		// instead — which is why there is no ceiling to trip for those.
		return $provider->with_tool_search( $definitions );
	}
}
