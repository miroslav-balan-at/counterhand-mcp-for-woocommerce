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
 *
 * A turn that asks for a change the person must approve is parked before any
 * of it runs, and resumed only once they have decided.
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

	public function __construct(
		private ToolDispatcherInterface $dispatcher,
		private PendingConfirmationStore $pending_store,
	) {}

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
		return $this->converse( $provider, $config, array_merge( $history, [ $provider->user_message( $user_text ) ] ), [], $agent );
	}

	/**
	 * Picks a parked turn back up: the approved calls run, the declined ones are
	 * reported to the model as refused, and the conversation continues.
	 *
	 * @param list<array<string,mixed>> $history Provider-format messages, ending with the parked assistant turn.
	 */
	public function resume(
		ProviderInterface $provider,
		ProviderConfig $config,
		array $history,
		PendingConfirmation $pending,
		ApprovalDecisions $decisions,
		AuthenticatedAgent $agent
	): AgentLoopResult {
		$transcript = [];
		$results    = [];

		foreach ( $pending->calls as $call ) {
			$approved = ! $pending->needs_approval( $call ) || $decisions->approves( $call->id );
			$data     = $approved ? $this->tool_result( self::approved( $call, $pending ), $agent ) : self::declined();

			$transcript[] = self::transcript_entry( $call, $data, ! $approved );
			$results[]    = self::result_for( $call, $data );
		}

		return $this->converse( $provider, $config, array_merge( $history, $provider->tool_result_messages( $results ) ), $transcript, $agent );
	}

	/**
	 * @param list<array<string,mixed>> $messages
	 * @param list<array<string,mixed>> $transcript
	 */
	private function converse(
		ProviderInterface $provider,
		ProviderConfig $config,
		array $messages,
		array $transcript,
		AuthenticatedAgent $agent
	): AgentLoopResult {
		$tools       = $this->dispatcher->visible_for( $agent );
		$definitions = $this->tool_definitions( $provider, $tools );
		$gated_names = array_map(
			static fn ( ToolInterface $tool ): string => $tool->name(),
			array_filter( $tools, static fn ( ToolInterface $tool ): bool => $tool->requires_confirmation() )
		);
		$usage       = new TokenUsage();

		for ( $iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++ ) {
			$turn = $provider->complete( $messages, $definitions, $config );

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

			$gated_ids = array_values(
				array_map(
					static fn ( ToolCall $call ): string => $call->id,
					array_filter( $turn->tool_calls, static fn ( ToolCall $call ): bool => in_array( $call->name, $gated_names, true ) )
				)
			);

			// Nothing in the turn runs until the person has ruled on the part
			// that needs them — running the rest first would present a half-done
			// change as the thing they are approving.
			if ( [] !== $gated_ids ) {
				$pending = $this->pending_store->park( $agent->token->owner_user_id, self::without_model_confirmation( $turn->tool_calls, $gated_ids ), $gated_ids );

				return new AgentLoopResult( $messages, $transcript, $usage, $pending );
			}

			$results = [];
			foreach ( $turn->tool_calls as $call ) {
				$data = $this->tool_result( $call, $agent );

				$transcript[] = self::transcript_entry( $call, $data, false );
				$results[]    = self::result_for( $call, $data );
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

	/** @return array{data: mixed, is_error: bool} */
	private static function declined(): array {
		return [
			'data'     => [ 'error' => __( 'The store administrator declined this action.', 'counterhand-mcp-for-woocommerce' ) ],
			'is_error' => true,
		];
	}

	/**
	 * The model's own `confirm` carries no weight here: the argument is
	 * dropped when the turn is parked and set only by the person's approval.
	 *
	 * @param  list<ToolCall> $calls
	 * @param  list<string>   $gated_ids
	 * @return list<ToolCall>
	 */
	private static function without_model_confirmation( array $calls, array $gated_ids ): array {
		return array_map(
			static function ( ToolCall $call ) use ( $gated_ids ): ToolCall {
				if ( ! in_array( $call->id, $gated_ids, true ) ) {
					return $call;
				}

				$input = $call->input;
				unset( $input['confirm'] );

				return new ToolCall( $call->id, $call->name, $input );
			},
			$calls
		);
	}

	private static function approved( ToolCall $call, PendingConfirmation $pending ): ToolCall {
		if ( ! $pending->needs_approval( $call ) ) {
			return $call;
		}

		return new ToolCall(
			$call->id,
			$call->name,
			[
				...$call->input,
				'confirm' => true,
			]
		);
	}

	/**
	 * @param  array{data: mixed, is_error: bool} $data
	 * @return array<string, mixed>
	 */
	private static function transcript_entry( ToolCall $call, array $data, bool $declined ): array {
		return [
			'type'      => 'tool',
			'name'      => $call->name,
			'arguments' => $call->input,
			'result'    => $data['data'],
			'is_error'  => $data['is_error'],
			'declined'  => $declined,
		];
	}

	/** @param array{data: mixed, is_error: bool} $data */
	private static function result_for( ToolCall $call, array $data ): ToolResult {
		return new ToolResult(
			id: $call->id,
			name: $call->name,
			output: (string) wp_json_encode( $data['data'] ),
			is_error: $data['is_error'],
		);
	}

	/**
	 * Built once per exchange and reused across iterations: the visible set
	 * cannot change mid-conversation, and input_schema() asks WooCommerce for
	 * its route args, which is not work to repeat twelve times.
	 *
	 * @param  list<ToolInterface> $tools
	 * @throws ToolCallException When more tools are enabled than one request should carry.
	 * @return list<array<string,mixed>>
	 */
	private function tool_definitions( ProviderInterface $provider, array $tools ): array {
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
