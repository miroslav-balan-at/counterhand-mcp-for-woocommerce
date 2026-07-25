<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OAuth\View;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the standalone OAuth flow pages (consent, denied, error, connected).
 *
 * These are front-end pages shown mid-OAuth — not wp-admin screens — so they
 * ship their own minimal shell styled with the WordPress admin palette
 * (blue-50 #2271b1, gray-100 #1d2327, WCAG-AA shades) so they still feel like
 * part of the store's admin.
 */
final readonly class FlowPage {

	public const STATE_CONSENT   = 'consent';
	public const STATE_DENIED    = 'denied';
	public const STATE_ERROR     = 'error';
	public const STATE_CONNECTED = 'connected';

	/**
	 * @param string               $state    One of the STATE_* constants; picks the body partial.
	 * @param array<string, mixed> $context  Variables the body partial expects.
	 */
	public function render( string $state, string $title, array $context = [] ): void {
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		$body_template = __DIR__ . '/templates/' . $state . '.php';
		if ( ! is_readable( $body_template ) ) {
			$body_template = __DIR__ . '/templates/error.php';
		}

		// Exposed to shell.php and, through it, to the body partial.
		$page_title = $title;
		$store_name = get_bloginfo( 'name' );
		$store_logo = self::store_logo_url();
		$store_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		include __DIR__ . '/templates/shell.php';
	}

	/**
	 * The store's own mark — the strongest trust signal on a consent page.
	 * Square site icon first, then the theme's custom logo; null means the
	 * shell falls back to a lettermark.
	 */
	private static function store_logo_url(): ?string {
		$site_icon = get_site_icon_url( 96 );
		if ( is_string( $site_icon ) && '' !== $site_icon ) {
			return $site_icon;
		}

		$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id > 0 ) {
			$custom_logo = wp_get_attachment_image_url( $custom_logo_id, 'medium' );
			if ( is_string( $custom_logo ) && '' !== $custom_logo ) {
				return $custom_logo;
			}
		}

		return null;
	}

	/** Renders an error page and terminates the request with an HTTP status. */
	public function render_error( string $headline, string $detail, int $status = 400 ): never {
		status_header( $status );

		$this->render(
			self::STATE_ERROR,
			__( 'Connection problem', 'agentgate-mcp-for-woocommerce' ),
			[
				'headline' => $headline,
				'detail'   => $detail,
			]
		);

		exit;
	}
}
