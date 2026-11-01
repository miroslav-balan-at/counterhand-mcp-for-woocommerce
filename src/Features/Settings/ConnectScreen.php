<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Shared\CanonicalUri;

defined( 'ABSPATH' ) || exit;

/**
 * Adding an app, and reviewing the apps already added.
 */
final readonly class ConnectScreen implements ScreenBodyInterface {

	public function __construct(
		private ScreenBodyInterface $connections,
		private ConnectionMatcher $matcher,
	) {}

	public function render_body(): void {
		$active = ConnectTab::current();
		$counts = [ ConnectTab::Connections->value => $this->matcher->live_count() ];

		require __DIR__ . '/views/connect-navigation.php';

		if ( ConnectTab::Connections === $active ) {
			$this->connections->render_body();

			return;
		}

		$endpoint_url      = CanonicalUri::mcp();
		$fallback_url      = rest_url( 'counterhand/v1/mcp' );
		$connect_clients   = McpClient::all( $endpoint_url );
		$connected_clients = $this->matcher->connected( $connect_clients );

		require __DIR__ . '/views/connect.php';
	}
}
