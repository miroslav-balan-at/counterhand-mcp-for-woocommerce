<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\Playground\Provider;

use AgentGateMcp\Features\Playground\Provider\AnthropicProvider;
use AgentGateMcp\Features\Playground\Provider\OpenAiCompatibleProvider;
use AgentGateMcp\Features\Playground\Provider\ProviderConfig;
use AgentGateMcp\Tests\Unit\TestCase;

/**
 * is_ready() drives which screen the Chat tab shows, so each provider's
 * required-field matrix is pinned here.
 */
final class ProviderReadinessTest extends TestCase {

	private function config( string $key = '', string $model = '', string $base_url = '' ): ProviderConfig {
		return new ProviderConfig(
			api_key: $key,
			model: $model,
			base_url: $base_url,
			system_prompt: '',
		);
	}

	public function test_anthropic_needs_key_and_model(): void {
		$provider = new AnthropicProvider();

		self::assertTrue( $provider->is_ready( $this->config( 'sk-x', 'claude-opus-5' ) ) );
		self::assertFalse( $provider->is_ready( $this->config( '', 'claude-opus-5' ) ) );
		self::assertFalse( $provider->is_ready( $this->config( 'sk-x', '' ) ) );
	}

	public function test_openai_needs_key_and_model(): void {
		$provider = OpenAiCompatibleProvider::openai();

		self::assertTrue( $provider->is_ready( $this->config( 'sk-x', 'gpt-5' ) ) );
		self::assertFalse( $provider->is_ready( $this->config( '', 'gpt-5' ) ) );
	}

	public function test_ollama_needs_only_a_model(): void {
		$provider = OpenAiCompatibleProvider::ollama();

		self::assertTrue( $provider->is_ready( $this->config( '', 'llama3' ) ) );
		self::assertFalse( $provider->is_ready( $this->config( '', '' ) ) );
	}

	public function test_custom_endpoint_needs_model_and_base_url(): void {
		$provider = OpenAiCompatibleProvider::compatible();

		self::assertTrue( $provider->is_ready( $this->config( '', 'mistral', 'http://10.0.0.2:8080/v1' ) ) );
		self::assertFalse( $provider->is_ready( $this->config( '', 'mistral', '' ) ) );
	}
}
