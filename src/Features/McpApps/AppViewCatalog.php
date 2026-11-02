<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpApps;

use Counterhand\Features\McpServer\AppViewContents;
use Counterhand\Features\McpServer\AppViewSourceInterface;
use Counterhand\Features\McpServer\ToolDispatcherInterface;
use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The views shipped with this plugin, each showing what one tool returned.
 *
 * They only display. A button that wrote would send the same confirm a model can set,
 * so the server could not tell a person's click from an injected instruction, and the
 * spec lets a host forward such a call without asking anyone.
 */
final readonly class AppViewCatalog implements AppViewSourceInterface {

	private const URI_PREFIX = 'ui://counterhand/';

	/** View name => the tool whose result it shows. */
	private const VIEWS = [
		'sales-report'   => 'get_sales_report',
		'order'          => 'get_order',
		'change-preview' => 'update_product',
	];

	public function __construct( private ToolDispatcherInterface $tools ) {}

	public function uri_for_tool( string $tool_name ): ?string {
		$view = array_search( $tool_name, self::VIEWS, true );

		return false === $view ? null : self::URI_PREFIX . $view;
	}

	public function read( string $uri, AuthenticatedAgent $agent ): ?AppViewContents {
		$view = str_starts_with( $uri, self::URI_PREFIX ) ? substr( $uri, strlen( self::URI_PREFIX ) ) : '';
		$tool = self::VIEWS[ $view ] ?? null;

		if ( null === $tool || ! $this->reaches( $agent, $tool ) ) {
			return null;
		}

		return new AppViewContents( $uri, $this->page( $view ) );
	}

	private function reaches( AuthenticatedAgent $agent, string $tool_name ): bool {
		return [] !== array_filter(
			$this->tools->visible_for( $agent ),
			static fn ( ToolInterface $tool ): bool => $tool->name() === $tool_name
		);
	}

	private function page( string $view ): string {
		$settings = [
			'version'  => COUNTERHAND_VERSION,
			'currency' => (string) get_option( 'woocommerce_currency' ),
			'text'     => ViewText::all(),
		];

		return sprintf(
			'<!doctype html><html lang="%1$s"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>%2$s</title><style>%3$s</style></head><body><main id="counterhand-view" class="is-loading" aria-busy="true"></main><script>window.counterhandSettings=%4$s;%5$s%6$s</script></body></html>',
			esc_attr( str_replace( '_', '-', get_locale() ) ),
			esc_html__( 'Counterhand', 'counterhand-mcp-for-woocommerce' ),
			$this->asset( 'view.css' ),
			(string) wp_json_encode( $settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ),
			$this->asset( 'bridge.js' ),
			$this->asset( $view . '.js' )
		);
	}

	private function asset( string $file ): string {
		return (string) file_get_contents( COUNTERHAND_PLUGIN_DIR . '/assets/mcp-apps/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file this plugin ships, inlined because a view may load nothing from elsewhere.
	}
}
