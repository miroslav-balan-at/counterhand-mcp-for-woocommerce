<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth\View;

use Counterhand\Shared\StoreMark;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the standalone OAuth flow pages (consent, denied, error, connected).
 *
 * These are front-end pages shown mid-OAuth — not wp-admin screens — so they
 * ship their own minimal shell. It is styled from assets/shared/tokens.css,
 * the same sheet the admin screens use, so the flow still reads as part of the
 * store's admin and follows the same colour scheme.
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
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $context is read by the included templates, which inherit this scope.
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
		$store_logo = StoreMark::url();
		$store_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		include __DIR__ . '/templates/shell.php';
	}

	/** Renders an error page and terminates the request with an HTTP status. */
	public function render_error( string $headline, string $detail, int $status = 400 ): never {
		status_header( $status );

		$this->render(
			self::STATE_ERROR,
			__( 'Connection problem', 'counterhand-mcp-for-woocommerce' ),
			[
				'headline' => $headline,
				'detail'   => $detail,
			]
		);

		exit;
	}
}
