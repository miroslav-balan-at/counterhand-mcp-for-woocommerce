<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Playground\Provider;

use AgentGateMcp\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

/**
 * Adapter for any endpoint speaking the OpenAI chat-completions contract:
 * OpenAI itself, OpenRouter, Azure OpenAI, Ollama, LM Studio, vLLM.
 *
 * They share the same tool-calling shape (`tools[].function`, assistant
 * `tool_calls`, `role: tool` results), so one adapter serves them all — the
 * base URL is what differs.
 */
final readonly class OpenAiCompatibleProvider implements ProviderInterface {

	public function __construct(
		private string $id,
		private string $label,
		private string $default_base_url,
		private array $models,
		private bool $base_url_required,
	) {}

	public static function openai(): self {
		return new self(
			id: 'openai',
			label: __( 'OpenAI', 'agentgate-mcp-for-woocommerce' ),
			default_base_url: 'https://api.openai.com/v1',
			models: [
				'gpt-5'      => 'GPT-5',
				'gpt-5-mini' => 'GPT-5 mini',
				'gpt-4.1'    => 'GPT-4.1',
			],
			base_url_required: false,
		);
	}

	/** Any self-hosted or third-party endpoint; the admin supplies the URL. */
	public static function compatible(): self {
		return new self(
			id: 'openai_compatible',
			label: __( 'OpenAI-compatible (Ollama, OpenRouter, Azure, local)', 'agentgate-mcp-for-woocommerce' ),
			default_base_url: 'http://localhost:11434/v1',
			models: [],
			base_url_required: true,
		);
	}

	public function id(): string {
		return $this->id;
	}

	public function label(): string {
		return $this->label;
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

			$tool_calls[] = [
				'id'    => (string) ( $call['id'] ?? '' ),
				'name'  => (string) ( $call['function']['name'] ?? '' ),
				'input' => is_array( $arguments ) ? $arguments : [],
			];
		}

		return new ProviderTurn(
			text: trim( (string) ( $message['content'] ?? '' ) ),
			tool_calls: $tool_calls,
			wants_tools: [] !== $tool_calls,
			raw: $message,
			usage: [
				'input'  => (int) ( $payload['usage']['prompt_tokens'] ?? 0 ),
				'output' => (int) ( $payload['usage']['completion_tokens'] ?? 0 ),
			],
		);
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
			static fn ( array $result ): array => [
				'role'         => 'tool',
				'tool_call_id' => $result['id'],
				'content'      => $result['output'],
			],
			$results
		);
	}

	public function user_message( string $text ): array {
		return [
			'role'    => 'user',
			'content' => $text,
		];
	}

	private function decode( mixed $response ): array {
		if ( is_wp_error( $response ) ) {
			throw new ToolCallException(
				sprintf(
				/* translators: %s: transport error message */
					__( 'Could not reach the model endpoint: %s', 'agentgate-mcp-for-woocommerce' ),
					$response->get_error_message()
				)
			);
		}

		$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$status  = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			throw new ToolCallException(
				sprintf(
				/* translators: 1: HTTP status, 2: API error message */
					__( 'The model endpoint returned %1$d: %2$s', 'agentgate-mcp-for-woocommerce' ),
					$status,
					(string) ( $payload['error']['message'] ?? __( 'unknown error', 'agentgate-mcp-for-woocommerce' ) )
				)
			);
		}

		return is_array( $payload ) ? $payload : [];
	}
}
