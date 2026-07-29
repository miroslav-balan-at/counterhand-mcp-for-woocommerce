<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Infrastructure;

use AgentGateMcp\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

/**
 * A REST route as a template, e.g. WcV3 + "/products/{id}".
 *
 * Tools declare the template, never a concrete path, so the same value can be
 * dispatched, looked up in the route catalog and handed to the permission
 * probe. Concrete ids only exist for the length of one dispatch() call.
 */
final readonly class RestRoute {

	private const PLACEHOLDER = '/\{(\w+)\}/';

	private function __construct(
		public RestNamespace $rest_namespace,
		public string $template,
	) {}

	public static function wc( string $template ): self {
		return new self( RestNamespace::WcV3, $template );
	}

	public static function wp( string $template ): self {
		return new self( RestNamespace::WpV2, $template );
	}

	/**
	 * Namespaced template, e.g. "/wc/v3/products/{id}".
	 *
	 * This is the form WordPress' own route normalization produces, so it
	 * doubles as the route-catalog key.
	 */
	public function path_template(): string {
		return $this->rest_namespace->prefix() . $this->template;
	}

	/**
	 * Placeholder names in declaration order.
	 *
	 * @return list<string>
	 */
	public function parameters(): array {
		preg_match_all( self::PLACEHOLDER, $this->template, $matches );

		return $matches[1];
	}

	/**
	 * Dispatchable path with every {placeholder} substituted.
	 *
	 * @param array $params Tool arguments; path values are read from here by name.
	 * @throws ToolCallException When a placeholder has no usable value.
	 */
	public function bind( array $params ): string {
		$path = $this->template;

		foreach ( $this->parameters() as $name ) {
			$value = $params[ $name ] ?? null;

			if ( ! is_scalar( $value ) || '' === (string) $value ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
				throw new ToolCallException(
					sprintf( 'Missing required "%s" argument.', $name )
				);
				// phpcs:enable
			}

			$path = str_replace( '{' . $name . '}', rawurlencode( (string) $value ), $path );
		}

		return $this->rest_namespace->prefix() . $path;
	}

	/**
	 * The same params with path placeholders removed.
	 *
	 * Path values reach the controller through the router's URL params, so
	 * resending them as query/body params is at best noise and at worst a
	 * schema violation on write routes.
	 */
	public function strip_path_params( array $params ): array {
		return array_diff_key( $params, array_flip( $this->parameters() ) );
	}
}
