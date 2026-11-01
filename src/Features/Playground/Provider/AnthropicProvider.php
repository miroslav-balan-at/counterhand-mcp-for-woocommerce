<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Anthropic Messages API adapter.
 *
 * Uses raw HTTP via wp_remote_post rather than the PHP SDK: the plugin ships
 * no Composer runtime dependencies, so a vendored SDK would risk the very
 * cross-plugin collisions we avoid elsewhere.
 */
final readonly class AnthropicProvider extends HttpProvider {

	private const ENDPOINT    = 'https://api.anthropic.com/v1/messages'; // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- fallback for WordPress below 7.0; the AI Client is preferred when present.
	private const API_VERSION = '2023-06-01';

	/**
	 * How many tools stay loaded up front once the catalogue is searchable.
	 *
	 * Anthropic recommends keeping the three-to-five most-used tools eager so
	 * routine questions cost no search round trip.
	 */
	private const EAGER_TOOLS = 5;

	public function id(): string {
		return 'anthropic';
	}

	public function label(): string {
		return __( 'Anthropic (Claude)', 'counterhand-mcp-for-woocommerce' );
	}

	public function default_models(): array {
		return [
			'claude-opus-5-5'   => 'Claude Opus 5.5',
			'claude-fable-5-1'  => 'Claude Fable 5.1',
			'claude-sonnet-5-5' => 'Claude Sonnet 5.5',
			'claude-haiku-5-5'  => 'Claude Haiku 5.5',
		];
	}

	public function needs_base_url(): bool {
		return false;
	}

	public function default_base_url(): string {
		return '';
	}

	public function needs_key(): bool {
		return true;
	}

	public function key_url(): string {
		return 'https://console.anthropic.com/settings/keys';
	}

	public function is_ready( ProviderConfig $config ): bool {
		return '' !== $config->api_key && '' !== $config->model;
	}

	public function complete( array $messages, array $tools, ProviderConfig $config ): ProviderTurn {
		$body = [
			'model'         => $config->model,
			'max_tokens'    => $config->max_tokens,
			'system'        => $config->system_prompt,
			'messages'      => $messages,
			// Every step of a turn resends tools, system and history, and only the last message is new.
			'cache_control' => [ 'type' => 'ephemeral' ],
		];

		if ( [] !== $tools ) {
			$body['tools'] = $tools;
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			[
				'headers' => [
					'content-type'      => 'application/json',
					'x-api-key'         => $config->api_key,
					'anthropic-version' => self::API_VERSION,
				],
				'body'    => (string) wp_json_encode( $body ),
				'timeout' => 120,
			]
		);

		$payload = $this->decode( $response );

		$text       = '';
		$tool_calls = [];

		foreach ( $payload['content'] ?? [] as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$text .= (string) $block['text'];
			}

			if ( 'tool_use' === ( $block['type'] ?? '' ) ) {
				$tool_calls[] = new ToolCall(
					id: (string) $block['id'],
					name: (string) $block['name'],
					input: is_array( $block['input'] ?? null ) ? $block['input'] : [],
				);
			}
		}

		$stop_reason = $payload['stop_reason'] ?? '';

		if ( 'refusal' === $stop_reason && '' === trim( $text ) ) {
			$text = __( 'The model declined to answer this.', 'counterhand-mcp-for-woocommerce' );
		}

		return new ProviderTurn(
			text: trim( $text ),
			tool_calls: $tool_calls,
			// phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- already Yoda; the sniff misreads the named argument.
			wants_tools: 'tool_use' === $stop_reason,
			raw: $payload,
			// input_tokens leaves out what the cache served or stored.
			usage: new TokenUsage(
				input: (int) ( $payload['usage']['input_tokens'] ?? 0 ) + (int) ( $payload['usage']['cache_read_input_tokens'] ?? 0 ) + (int) ( $payload['usage']['cache_creation_input_tokens'] ?? 0 ),
				output: (int) ( $payload['usage']['output_tokens'] ?? 0 ),
			),
			// Its server-side tool search hit a limit: sending the turn back resumes it.
			paused: 'pause_turn' === $stop_reason,
		);
	}

	public function describe_tool( string $name, string $description, array $input_schema ): array {
		return [
			'name'         => $name,
			'description'  => $description,
			'input_schema' => $input_schema,
		];
	}

	/** No ceiling: past the eager threshold the catalogue is searched instead. */
	public function max_eager_tools(): ?int {
		return null;
	}

	/**
	 * Publishes a large tool set as a searchable catalogue.
	 *
	 * Anthropic's own guidance is that selection accuracy falls off past 30–50
	 * eagerly-loaded tools, and that the fix is deferring the tail rather than
	 * refusing the request: Claude searches names, descriptions and argument
	 * names, and the API expands what it finds. Deferred definitions are left
	 * out of the system-prompt prefix, so the schemas cost nothing until a
	 * search surfaces them and the prompt cache survives.
	 *
	 * @param  list<array<string,mixed>> $tools
	 * @return list<array<string,mixed>>
	 */
	public function with_tool_search( array $tools ): array {
		if ( count( $tools ) <= self::EAGER_TOOLS ) {
			return $tools;
		}

		$catalogue = [
			[
				'type' => 'tool_search_tool_bm25_20251119',
				'name' => 'tool_search_tool_bm25',
			],
		];

		// The first few stay eager so the common questions need no search at
		// all; the API rejects a request in which everything is deferred.
		foreach ( $tools as $index => $tool ) {
			if ( $index >= self::EAGER_TOOLS ) {
				$tool['defer_loading'] = true;
			}

			$catalogue[] = $tool;
		}

		return $catalogue;
	}

	public function assistant_message( ProviderTurn $turn ): array {
		// Echo the assistant content verbatim so tool_use blocks keep their ids.
		return [
			'role'    => 'assistant',
			'content' => $turn->raw['content'] ?? [],
		];
	}

	public function tool_result_messages( array $results ): array {
		$blocks = [];

		foreach ( $results as $result ) {
			$blocks[] = [
				'type'        => 'tool_result',
				'tool_use_id' => $result->id,
				'content'     => $result->output,
				'is_error'    => $result->is_error,
			];
		}

		// All results for one assistant turn must ride in a single user message.
		return [
			[
				'role'    => 'user',
				'content' => $blocks,
			],
		];
	}

	protected function unreachable_error(): string {
		/* translators: %s: transport error message */
		return __( 'Could not reach Anthropic: %s', 'counterhand-mcp-for-woocommerce' );
	}

	protected function api_error(): string {
		/* translators: 1: HTTP status, 2: API error message */
		return __( 'Anthropic returned %1$d: %2$s', 'counterhand-mcp-for-woocommerce' );
	}
}
