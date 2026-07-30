<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * The set of LLM providers the chat can talk to.
 */
final readonly class ProviderRegistry {

	/** @var array<string, ProviderInterface> */
	private array $providers;

	public function __construct() {
		$providers = [];

		/*
		 * Core's client goes first so it is the default offer: when WordPress
		 * provides it, the store owner connects a model without this plugin
		 * ever handling an API key. The direct adapters stay available for
		 * older WordPress and for anyone who would rather use their own key.
		 */
		if ( CoreAiClientProvider::is_available() ) {
			$providers[] = new CoreAiClientProvider();
		}

		$providers = array_merge(
			$providers,
			[
				new AnthropicProvider(),
				OpenAiCompatibleProvider::openai(),
				OpenAiCompatibleProvider::google(),
				OpenAiCompatibleProvider::ollama(),
				OpenAiCompatibleProvider::compatible(),
			]
		);

		/**
		 * Filters the available chat providers.
		 *
		 * @param list<ProviderInterface> $providers Provider adapters.
		 */
		$providers = apply_filters( 'ctrh_chat_providers', $providers );

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

	/** The provider offered first: core's client whenever WordPress has one. */
	public function default_id(): string {
		return (string) ( array_key_first( $this->providers ) ?? 'anthropic' );
	}

	/** @return array<string, ProviderInterface> */
	public function all(): array {
		return $this->providers;
	}

	/**
	 * Providers the admin connects with their own account — what the chooser's
	 * bring-your-own-key card offers. Core-managed ones are offered separately.
	 *
	 * @return array<string, ProviderInterface>
	 */
	public function user_configured(): array {
		return array_filter(
			$this->providers,
			static fn ( ProviderInterface $provider ): bool => $provider->is_user_configured()
		);
	}
}
