<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth;

use Counterhand\Features\OAuth\View\FlowPage;

defined( 'ABSPATH' ) || exit;

/**
 * Hands the authorization result back to the client's callback.
 *
 * If the callback lives on this very site it is not a real client endpoint
 * (a stray or placeholder redirect_uri), which would dump the user on a 404.
 * In that case we render the outcome page instead so the flow always ends on
 * a designed screen.
 */
final readonly class CallbackRedirector {

	public function __construct( private FlowPage $page ) {}

	/**
	 * @param array<string, string> $params           Query args for the callback.
	 * @param string                $fallback_state   FlowPage::STATE_* to render if there is no usable callback.
	 * @param array<string, mixed>  $fallback_context Context for that page.
	 */
	public function redirect_with(
		string $redirect_uri,
		array $params,
		string $fallback_state,
		string $fallback_title,
		array $fallback_context
	): never {
		if ( $this->is_self_hosted_callback( $redirect_uri ) ) {
			$this->page->render( $fallback_state, $fallback_title, $fallback_context );
			exit;
		}

		wp_redirect( add_query_arg( array_map( 'rawurlencode', array_filter( $params, static fn ( $value ): bool => '' !== $value ) ), $redirect_uri ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- OAuth redirect to a CIMD-validated client URI, not an internal page.
		exit;
	}

	private function is_self_hosted_callback( string $redirect_uri ): bool {
		$callback_host = wp_parse_url( $redirect_uri, PHP_URL_HOST );
		$site_host     = wp_parse_url( home_url(), PHP_URL_HOST );

		return is_string( $callback_host ) && is_string( $site_host )
			&& strtolower( $callback_host ) === strtolower( $site_host );
	}
}
