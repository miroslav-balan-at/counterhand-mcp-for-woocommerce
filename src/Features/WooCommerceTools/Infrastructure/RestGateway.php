<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Infrastructure;

use Counterhand\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

/**
 * Internal dispatch into WordPress' REST controllers.
 *
 * rest_do_request() is a PHP-level dispatch (no HTTP loopback), so we inherit
 * WooCommerce's validation, sanitization, HPOS-safe order access and
 * permission checks — evaluated against the token owner set as current user.
 */
final readonly class RestGateway implements RestGatewayInterface {

	/**
	 * @throws ToolCallException On any non-2xx response, with an agent-actionable message.
	 */
	public function dispatch( RestRoute $route, RestMethod $method, array $params = [], mixed $body = null ): RestResult {
		$request = new \WP_REST_Request( $method->value, $route->bind( $params ) );

		foreach ( $route->strip_path_params( $params ) as $key => $value ) {
			$request->set_param( (string) $key, $value );
		}

		// set_param() is invisible to get_json_params(), so a controller reading
		// the body sees nothing and treats the request as an empty one.
		if ( null !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			$error = $response->as_error();

			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException(
				sprintf(
					'WooCommerce rejected the request (%s): %s',
					(string) $error->get_error_code(),
					$error->get_error_message()
				)
			);
			// phpcs:enable
		}

		// WordPress hooks rest_filter_response_fields() to rest_post_dispatch,
		// which only WP_REST_Server::serve_request() fires — never dispatch().
		// Calling it here is what makes `_fields` do anything at all: without
		// it WooCommerce still prunes its own expensive fields, but the wire
		// payload comes back whole.
		$response = rest_filter_response_fields( $response, rest_get_server(), $request );

		$data = rest_get_server()->response_to_data( $response, false );

		if ( ! is_array( $data ) ) {
			return new RestResult( [ 'value' => $data ] );
		}

		// response_to_data() re-attaches HAL links after field filtering. They
		// are HTTP-client affordances an MCP agent cannot follow, and on a
		// collection the string key would hide the payload's list shape.
		unset( $data['_links'] );

		return new RestResult(
			$data,
			$this->int_header( $response, 'X-WP-Total' ),
			$this->int_header( $response, 'X-WP-TotalPages' )
		);
	}

	/** HTTP header names are case-insensitive; WP stores them verbatim. */
	private function int_header( \WP_REST_Response $response, string $name ): ?int {
		foreach ( $response->get_headers() as $key => $value ) {
			if ( 0 === strcasecmp( (string) $key, $name ) ) {
				return (int) $value;
			}
		}

		return null;
	}
}
