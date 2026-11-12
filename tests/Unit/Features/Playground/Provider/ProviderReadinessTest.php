<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Playground\Provider;

use Counterhand\Features\Playground\Provider\AnthropicProvider;
use Counterhand\Features\Playground\Provider\OpenAiCompatibleProvider;
use Counterhand\Features\Playground\Provider\ProviderConfig;
use Counterhand\Tests\Unit\TestCase;

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

	/**
	 * @return array<string, array{OpenAiCompatibleProvider, string}>
	 */
	public static function hosted_presets(): array {
		return [
			'xai'        => [ OpenAiCompatibleProvider::xai(), 'https://api.x.ai/v1' ],
			'mistral'    => [ OpenAiCompatibleProvider::mistral(), 'https://api.mistral.ai/v1' ],
			'deepseek'   => [ OpenAiCompatibleProvider::deepseek(), 'https://api.deepseek.com' ],
			'openrouter' => [ OpenAiCompatibleProvider::openrouter(), 'https://openrouter.ai/api/v1' ],
		];
	}

	/**
	 * @dataProvider hosted_presets
	 */
	public function test_a_hosted_preset_needs_key_and_model_but_no_address( OpenAiCompatibleProvider $provider, string $base_url ): void {
		self::assertSame( $base_url, $provider->default_base_url() );
		self::assertFalse( $provider->needs_base_url() );
		self::assertNotSame( '', $provider->key_url() );
		self::assertTrue( $provider->is_ready( $this->config( 'key', 'some-model' ) ) );
		self::assertFalse( $provider->is_ready( $this->config( '', 'some-model' ) ) );
		self::assertFalse( $provider->is_ready( $this->config( 'key', '' ) ) );
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
