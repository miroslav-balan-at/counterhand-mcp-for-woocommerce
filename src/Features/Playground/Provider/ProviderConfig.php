<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Playground\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Per-request provider settings: credentials, model, endpoint.
 */
final readonly class ProviderConfig {

	public function __construct(
		public string $api_key,
		public string $model,
		public string $base_url,
		public string $system_prompt,
		public int $max_tokens = 8192,
	) {}
}
