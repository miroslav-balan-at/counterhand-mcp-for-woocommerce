<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Playground\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * The set of LLM providers the chat can talk to.
 */
final readonly class ProviderRegistry {

	/** @var array<string, ProviderInterface> */
	private array $providers;

	public function __construct() {
		$providers = [
			new AnthropicProvider(),
			OpenAiCompatibleProvider::openai(),
			OpenAiCompatibleProvider::compatible(),
		];

		/**
		 * Filters the available chat providers.
		 *
		 * @param list<ProviderInterface> $providers Provider adapters.
		 */
		$providers = apply_filters( 'agmcp_chat_providers', $providers );

		$keyed = [];
		foreach ( $providers as $provider ) {
			if ( $provider instanceof ProviderInterface ) {
				$keyed[ $provider->id() ] = $provider;
			}
		}

		$this->providers = $keyed;
	}

	public function get( string $id ): ?ProviderInterface {
		return $this->providers[ $id ] ?? null;
	}

	/** @return array<string, ProviderInterface> */
	public function all(): array {
		return $this->providers;
	}
}
