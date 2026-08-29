<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Adapter for any endpoint speaking the OpenAI chat-completions contract:
 * OpenAI itself, OpenRouter, Azure OpenAI, Ollama, LM Studio, vLLM.
 *
 * They share the same tool-calling shape (`tools[].function`, assistant
 * `tool_calls`, `role: tool` results), so one adapter serves them all — the
 * base URL is what differs.
 */
final readonly class OpenAiCompatibleProvider extends HttpProvider {

	/** @param \Closure(): string $label */
	public function __construct(
		private string $id,
		private \Closure $label,
		private string $default_base_url,
		private array $models,
		private bool $base_url_required,
		private string $key_url = '',
		private bool $key_required = true,
	) {}

	public static function openai(): self {
		return new self(
			id: 'openai',
			label: static fn (): string => __( 'ChatGPT (OpenAI)', 'counterhand-mcp-for-woocommerce' ),
			default_base_url: 'https://api.openai.com/v1', // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- fallback for WordPress below 7.0; the AI Client is preferred when present.
			models: [
				'gpt-5'      => 'GPT-5',
				'gpt-5-mini' => 'GPT-5 mini',
				'gpt-4.1'    => 'GPT-4.1',
			],
			base_url_required: false,
			key_url: 'https://platform.openai.com/api-keys',
		);
	}

	/**
	 * Gemini through Google's OpenAI-compatible endpoint, so it needs no
	 * adapter of its own — only a different base URL.
	 */
	public static function google(): self {
		return new self(
			id: 'google',
			label: static fn (): string => __( 'Gemini (Google)', 'counterhand-mcp-for-woocommerce' ),
			default_base_url: 'https://generativelanguage.googleapis.com/v1beta/openai/', // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- fallback for WordPress below 7.0; the AI Client is preferred when present.
			// Google renames models often; the picker is a starting point and
			// the field accepts whatever the account actually offers.
			models: [
				'gemini-3-flash-preview' => 'Gemini 3 Flash',
				'gemini-2.5-pro'         => 'Gemini 2.5 Pro',
			],
			base_url_required: false,
			key_url: 'https://aistudio.google.com/apikey',
		);
	}

	/** A model running on the same machine: no account, no key, no data leaving. */
	public static function ollama(): self {
		return new self(
			id: 'ollama',
			label: static fn (): string => __( 'Ollama (on this server)', 'counterhand-mcp-for-woocommerce' ),
			default_base_url: 'http://localhost:11434/v1',
			models: [],
			base_url_required: false,
			key_required: false,
		);
	}

	/** Any other self-hosted or third-party endpoint; the admin supplies the URL. */
	public static function compatible(): self {
		return new self(
			id: 'openai_compatible',
			label: static fn (): string => __( 'Custom OpenAI-compatible endpoint', 'counterhand-mcp-for-woocommerce' ),
			default_base_url: '',
			models: [],
			base_url_required: true,
			key_required: false,
		);
	}

	public function id(): string {
		return $this->id;
	}

	public function label(): string {
		return ( $this->label )();
	}

	public function default_models(): array {
		return $this->models;
	}

	public function needs_base_url(): bool {
		return $this->base_url_required;
	}

	public function default_base_url(): string {
		return $this->default_base_url;
	}

	public function needs_key(): bool {
		return $this->key_required;
	}

	public function key_url(): string {
		return $this->key_url;
	}

	public function is_ready( ProviderConfig $config ): bool {
		if ( '' === $config->model ) {
			return false;
		}

		if ( $this->key_required && '' === $config->api_key ) {
			return false;
		}

		return ! $this->base_url_required || '' !== $config->base_url;
	}

	public function complete( array $messages, array $tools, ProviderConfig $config ): ProviderTurn {
		$base = untrailingslashit( '' !== $config->base_url ? $config->base_url : $this->default_base_url );

		$body = [
			'model'    => $config->model,
			'messages' => array_merge(
				[
					[
						'role'    => 'system',
						'content' => $config->system_prompt,
					],
				],
				$messages
			),
		];

		if ( [] !== $tools ) {
			$body['tools'] = $tools;
		}

		$headers = [ 'content-type' => 'application/json' ];

		// Local endpoints such as Ollama accept requests without a key.
		if ( '' !== $config->api_key ) {
			$headers['authorization'] = 'Bearer ' . $config->api_key;
		}

		$response = wp_remote_post(
			$base . '/chat/completions',
			[
				'headers' => $headers,
				'body'    => (string) wp_json_encode( $body ),
				'timeout' => 120,
			]
		);

		$payload = $this->decode( $response );
		$message = $payload['choices'][0]['message'] ?? [];

		$tool_calls = [];
		foreach ( $message['tool_calls'] ?? [] as $call ) {
			$arguments = json_decode( (string) ( $call['function']['arguments'] ?? '{}' ), true );

			$tool_calls[] = new ToolCall(
				id: (string) ( $call['id'] ?? '' ),
				name: (string) ( $call['function']['name'] ?? '' ),
				input: is_array( $arguments ) ? $arguments : [],
			);
		}

		return new ProviderTurn(
			text: trim( (string) ( $message['content'] ?? '' ) ),
			tool_calls: $tool_calls,
			wants_tools: [] !== $tool_calls,
			raw: $message,
			usage: new TokenUsage(
				input: (int) ( $payload['usage']['prompt_tokens'] ?? 0 ),
				output: (int) ( $payload['usage']['completion_tokens'] ?? 0 ),
			),
		);
	}

	/**
	 * Well under OpenAI's hard limit of 128 tools per request, because the wire
	 * limit is not the useful one: accuracy falls off long before it, and this
	 * format has no deferred-loading escape hatch to move the ceiling.
	 */
	public function max_eager_tools(): int {
		return 60;
	}

	/** No searchable catalogue on this wire format; the set is sent as-is. */
	public function with_tool_search( array $tools ): array {
		return $tools;
	}

	public function describe_tool( string $name, string $description, array $input_schema ): array {
		return [
			'type'     => 'function',
			'function' => [
				'name'        => $name,
				'description' => $description,
				'parameters'  => $input_schema,
			],
		];
	}

	public function assistant_message( ProviderTurn $turn ): array {
		$message = [
			'role'    => 'assistant',
			'content' => $turn->raw['content'] ?? null,
		];

		if ( isset( $turn->raw['tool_calls'] ) ) {
			$message['tool_calls'] = $turn->raw['tool_calls'];
		}

		return $message;
	}

	public function tool_result_messages( array $results ): array {
		// One message per result, unlike Anthropic's single batched user turn.
		return array_map(
			static fn ( ToolResult $result ): array => [
				'role'         => 'tool',
				'tool_call_id' => $result->id,
				'content'      => $result->output,
			],
			$results
		);
	}

	protected function unreachable_error(): string {
		/* translators: %s: transport error message */
		return __( 'Could not reach the model endpoint: %s', 'counterhand-mcp-for-woocommerce' );
	}

	protected function api_error(): string {
		/* translators: 1: HTTP status, 2: API error message */
		return __( 'The model endpoint returned %1$d: %2$s', 'counterhand-mcp-for-woocommerce' );
	}
}
