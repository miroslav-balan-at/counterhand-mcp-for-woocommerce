<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Playground\Provider;

use AgentGateMcp\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

/**
 * Anthropic Messages API adapter.
 *
 * Uses raw HTTP via wp_remote_post rather than the PHP SDK: the plugin ships
 * no Composer runtime dependencies, so a vendored SDK would risk the very
 * cross-plugin collisions we avoid elsewhere.
 */
final readonly class AnthropicProvider implements ProviderInterface {

	private const ENDPOINT    = 'https://api.anthropic.com/v1/messages';
	private const API_VERSION = '2023-06-01';

	public function id(): string {
		return 'anthropic';
	}

	public function label(): string {
		return __( 'Anthropic (Claude)', 'agentgate-mcp-for-woocommerce' );
	}

	public function default_models(): array {
		return [
			'claude-opus-5'    => 'Claude Opus 5',
			'claude-sonnet-5'  => 'Claude Sonnet 5',
			'claude-haiku-4-5' => 'Claude Haiku 4.5',
		];
	}

	public function needs_base_url(): bool {
		return false;
	}

	public function complete( array $messages, array $tools, ProviderConfig $config ): ProviderTurn {
		$body = [
			'model'      => $config->model,
			'max_tokens' => $config->max_tokens,
			'system'     => $config->system_prompt,
			'messages'   => $messages,
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
				$tool_calls[] = [
					'id'    => (string) $block['id'],
					'name'  => (string) $block['name'],
					'input' => is_array( $block['input'] ?? null ) ? $block['input'] : [],
				];
			}
		}

		return new ProviderTurn(
			text: trim( $text ),
			tool_calls: $tool_calls,
			wants_tools: 'tool_use' === ( $payload['stop_reason'] ?? '' ),
			raw: $payload,
			usage: [
				'input'  => (int) ( $payload['usage']['input_tokens'] ?? 0 ),
				'output' => (int) ( $payload['usage']['output_tokens'] ?? 0 ),
			],
		);
	}

	public function describe_tool( string $name, string $description, array $input_schema ): array {
		return [
			'name'         => $name,
			'description'  => $description,
			'input_schema' => $input_schema,
		];
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
				'tool_use_id' => $result['id'],
				'content'     => $result['output'],
				'is_error'    => $result['is_error'],
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
					__( 'Could not reach Anthropic: %s', 'agentgate-mcp-for-woocommerce' ),
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
					__( 'Anthropic returned %1$d: %2$s', 'agentgate-mcp-for-woocommerce' ),
					$status,
					(string) ( $payload['error']['message'] ?? __( 'unknown error', 'agentgate-mcp-for-woocommerce' ) )
				)
			);
		}

		return is_array( $payload ) ? $payload : [];
	}
}
