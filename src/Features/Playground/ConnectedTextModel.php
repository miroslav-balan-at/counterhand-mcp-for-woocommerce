<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Features\Playground\Provider\ProviderConfig;
use Counterhand\Features\Playground\Provider\ProviderRegistry;
use Counterhand\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

final readonly class ConnectedTextModel implements TextModelInterface {

	public function __construct(
		private ChatSettings $settings,
		private ProviderRegistry $providers,
	) {}

	public function is_connected(): bool {
		return $this->settings->is_configured() && null !== $this->providers->get( $this->settings->provider_id() );
	}

	public function write( string $system_prompt, string $request, int $max_tokens ): string {
		$provider = $this->providers->get( $this->settings->provider_id() );
		if ( null === $provider || ! $this->settings->is_configured() ) {
			throw new ToolCallException( 'No model is connected.' );
		}

		return $provider->complete(
			[ $provider->user_message( $request ) ],
			[],
			new ProviderConfig(
				api_key: $this->settings->api_key(),
				model: $this->settings->model(),
				base_url: $this->settings->base_url(),
				system_prompt: $system_prompt,
				max_tokens: $max_tokens,
			)
		)->text;
	}
}
