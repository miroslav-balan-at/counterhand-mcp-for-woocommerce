<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Provider;

use Counterhand\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

/**
 * Shared plumbing for the direct wire adapters (Anthropic, OpenAI-compatible).
 *
 * Both speak JSON over wp_remote_post and fail the same way; only the wire
 * format and the vendor named in the error messages differ.
 */
abstract readonly class HttpProvider implements ProviderInterface {

	public function is_user_configured(): bool {
		return true;
	}

	/** One tiny completion — the cheapest proof that key and model both work. */
	public function test( ProviderConfig $config ): void {
		$this->complete(
			[ $this->user_message( 'ping' ) ],
			[],
			new ProviderConfig(
				api_key: $config->api_key,
				model: $config->model,
				base_url: $config->base_url,
				system_prompt: 'Reply with OK.',
				max_tokens: 16,
			)
		);
	}

	public function user_message( string $text ): array {
		return [
			'role'    => 'user',
			'content' => $text,
		];
	}

	/**
	 * Validates the transport result and returns the decoded JSON payload.
	 *
	 * @throws ToolCallException On transport failure or a non-200 response.
	 * @return array<string,mixed>
	 */
	protected function decode( mixed $response ): array {
		if ( is_wp_error( $response ) ) {
			throw new ToolCallException( esc_html( sprintf( $this->unreachable_error(), $response->get_error_message() ) ) );
		}

		$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$status  = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			throw new ToolCallException(
				esc_html(
					sprintf(
						$this->api_error(),
						$status,
						(string) ( $payload['error']['message'] ?? __( 'unknown error', 'counterhand-mcp-for-woocommerce' ) )
					)
				)
			);
		}

		return is_array( $payload ) ? $payload : [];
	}

	/** Translated sprintf template for a transport failure, naming this vendor; %s is the error detail. */
	abstract protected function unreachable_error(): string;

	/** Translated sprintf template for a non-200 response, naming this vendor; %1$d is the status, %2$s the API message. */
	abstract protected function api_error(): string;
}
