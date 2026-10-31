<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Infrastructure;

use Counterhand\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

/**
 * A REST route as a template, e.g. "wc/v3" + "/products/{id}".
 *
 * Tools declare the template, never a concrete path, so the same value can be
 * dispatched, looked up in the route catalog and handed to the permission
 * probe. Concrete ids only exist for the length of one dispatch() call.
 */
final readonly class RestRoute {

	private const PLACEHOLDER = '/\{(\w+)\}/';

	private const NAMESPACE_PATTERN = '#^(?!.*\.\.)[A-Za-z0-9_.-]+(/[A-Za-z0-9_.-]+)*$#';

	private function __construct(
		public string $rest_namespace,
		public string $template,
	) {}

	/**
	 * A route in any plugin's namespace, e.g. "contact-form-7/v1".
	 *
	 * Checked for shape only. Asking the REST server here would build it at
	 * plugins_loaded, and whether the route is served is answered per request by
	 * the tool's availability check anyway.
	 *
	 * @throws \InvalidArgumentException When it could not be a REST namespace.
	 */
	public static function of( string $rest_namespace, string $template ): self {
		if ( 1 !== preg_match( self::NAMESPACE_PATTERN, $rest_namespace ) ) {
			throw new \InvalidArgumentException( sprintf( '"%s" is not a REST namespace.', esc_html( $rest_namespace ) ) );
		}

		return new self( $rest_namespace, $template );
	}

	public static function wc( string $template ): self {
		return new self( 'wc/v3', $template );
	}

	public static function wp( string $template ): self {
		return new self( 'wp/v2', $template );
	}

	/**
	 * Namespaced template, e.g. "/wc/v3/products/{id}".
	 *
	 * This is the form WordPress' own route normalization produces, so it
	 * doubles as the route-catalog key.
	 */
	public function path_template(): string {
		return '/' . $this->rest_namespace . $this->template;
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

		return '/' . $this->rest_namespace . $path;
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
