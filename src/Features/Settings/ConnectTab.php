<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The two halves of the Connect screen.
 *
 * Tabs earn their place here, unlike across the plugin's screens: adding an app
 * and reviewing the apps already added are two views of the same subject, which
 * is what a tab set is for.
 */
enum ConnectTab: string {

	case Apps        = 'apps';
	case Connections = 'connections';

	public function label(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Apps        => __( 'Add an app', 'agentgate-mcp-for-woocommerce' ),
			self::Connections => __( 'Connected apps', 'agentgate-mcp-for-woocommerce' ),
		};
	}

	public function url(): string {
		return add_query_arg(
			[
				'page' => AdminScreen::Connect->value,
				'view' => $this->value, // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			],
			admin_url( 'admin.php' )
		);
	}

	public static function current(): self {
		$view = sanitize_key( $_GET['view'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- view routing only.

		return self::tryFrom( $view ) ?? self::Apps;
	}
}
