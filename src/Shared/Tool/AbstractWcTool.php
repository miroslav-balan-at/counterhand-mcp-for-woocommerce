<?php

declare( strict_types=1 );

namespace AgentGateMcp\Shared\Tool;

use AgentGateMcp\Shared\WooCommerce\ResponseShaper;
use AgentGateMcp\Shared\WooCommerce\RestGateway;

defined( 'ABSPATH' ) || exit;

/**
 * Base for tools that delegate to WooCommerce's wc/v3 REST controllers.
 */
abstract readonly class AbstractWcTool implements ToolInterface {

	public function __construct(
		protected RestGateway $gateway,
		protected ResponseShaper $shaper,
	) {}

	/** Standard pagination properties shared by every list tool. */
	protected function pagination_properties(): array {
		return [
			'page'     => [
				'type'        => 'integer',
				'minimum'     => 1,
				'default'     => 1,
				'description' => 'Result page, starting at 1.',
			],
			'per_page' => [
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => 50,
				'default'     => 10,
				'description' => 'Items per page (max 50).',
			],
		];
	}
}
