<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Infrastructure;

use Counterhand\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

/**
 * Port for dispatching into WordPress' REST controllers.
 *
 * Slice-owned rather than shared: nothing outside WooCommerceTools dispatches
 * REST. It exists so tools are testable without WordPress loaded — the concrete
 * gateway is a thin adapter over rest_do_request().
 */
interface RestGatewayInterface {

	/**
	 * @param array $params Tool arguments. Values matching a {placeholder} in
	 *                      $route are bound into the path; the rest travel as
	 *                      request params. A `_fields` entry prunes the
	 *                      response through WordPress' own field filter.
	 * @throws ToolCallException On any non-2xx response, with an agent-actionable message.
	 */
	public function dispatch( RestRoute $route, RestMethod $method, array $params = [] ): RestResult;
}
