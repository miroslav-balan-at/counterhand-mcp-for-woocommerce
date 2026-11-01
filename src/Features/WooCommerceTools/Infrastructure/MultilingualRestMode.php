<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\McpServer\ToolDispatcherInterface;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Multilingual adds its REST hooks only when the URL is /wp-json/wc/vN/, so
 * tool calls made in-process miss them: a changed price stayed old on every translation,
 * and a translation sent with lang was saved in the default language, unlinked, as a success.
 *
 * WooCommerce's authentication asks the same filter, and must not start taking API keys
 * on these URLs, so its answer stays as it was there.
 */
final readonly class MultilingualRestMode {

	private const FILTER = 'woocommerce_rest_is_request_to_rest_api';

	public function register(): void {
		add_filter( self::FILTER, [ $this, 'include_tool_requests' ] );
	}

	public function include_tool_requests( mixed $is_rest_request ): mixed {
		if ( ! defined( 'WCML_VERSION' ) || doing_filter( 'determine_current_user' ) || doing_filter( 'rest_authentication_errors' ) ) {
			return $is_rest_request;
		}

		return $is_rest_request || true === apply_filters( ToolDispatcherInterface::SERVES_TOOLS_FILTER, false );
	}
}
