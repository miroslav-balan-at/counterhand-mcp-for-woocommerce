<?php

declare( strict_types=1 );

namespace AgentGateMcp\Shared\WooCommerce;

use AgentGateMcp\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

/**
 * Internal dispatch into WooCommerce's wc/v3 REST controllers.
 *
 * rest_do_request() is a PHP-level dispatch (no HTTP loopback), so we inherit
 * WooCommerce's validation, sanitization, HPOS-safe order access and
 * permission checks — evaluated against the token owner set as current user.
 */
final readonly class RestGateway {

	/**
	 * @throws ToolCallException On any non-2xx response, with an agent-actionable message.
	 */
	public function dispatch( string $method, string $route, array $params = [] ): array {
		$request = new \WP_REST_Request( $method, '/wc/v3' . $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( (string) $key, $value );
		}

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			$error = $response->as_error();

			throw new ToolCallException( sprintf(
				'WooCommerce rejected the request (%s): %s',
				(string) $error->get_error_code(),
				$error->get_error_message()
			) );
		}

		$data = rest_get_server()->response_to_data( $response, false );

		return is_array( $data ) ? $data : [ 'value' => $data ];
	}
}
