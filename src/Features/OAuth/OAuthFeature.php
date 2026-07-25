<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OAuth;

use AgentGateMcp\Features\Settings\PluginSettings;
use AgentGateMcp\Features\Tokens\Domain\TokenRepositoryInterface;
use AgentGateMcp\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap for the OAuth 2.1 + CIMD slice: discovery docs, the login-gated
 * consent/authorize route, and the token endpoint.
 */
final readonly class OAuthFeature implements FeatureInterface {

	private const WELL_KNOWN_QUERY_VAR = 'agmcp_well_known';

	private DiscoveryController $discovery;
	private AuthorizeEndpoint $authorize;
	private TokenEndpoint $token;

	public function __construct(
		private PluginSettings $settings,
		TokenRepositoryInterface $repository,
	) {
		$code_store      = new AuthorizationCodeStore();
		$this->discovery = new DiscoveryController();
		$this->authorize = new AuthorizeEndpoint( new ClientMetadataResolver(), $code_store );
		$this->token     = new TokenEndpoint( $repository, $code_store );
	}

	public function register(): void {
		// OAuth only exists when the connector is on.
		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		add_action( 'init', [ self::class, 'register_rewrites' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'parse_request', [ $this, 'maybe_handle_well_known' ] );
		add_action( 'parse_request', [ $this->authorize, 'maybe_handle' ] );

		add_action( 'rest_api_init', [ $this->discovery, 'register_routes' ] );
		add_action( 'rest_api_init', [ $this->token, 'register_route' ] );
	}

	public static function register_rewrites(): void {
		AuthorizeEndpoint::register_rewrite();

		// RFC 9728 / RFC 8414 mandate the documents live at the domain root under
		// /.well-known/ — map both to our REST discovery handlers.
		add_rewrite_rule( '^\.well-known/oauth-protected-resource/?$', 'index.php?' . self::WELL_KNOWN_QUERY_VAR . '=resource', 'top' );
		add_rewrite_rule( '^\.well-known/oauth-authorization-server/?$', 'index.php?' . self::WELL_KNOWN_QUERY_VAR . '=server', 'top' );
	}

	public function add_query_vars( array $query_vars ): array {
		$query_vars[] = self::WELL_KNOWN_QUERY_VAR;
		$query_vars[] = AuthorizeEndpoint::QUERY_VAR;

		return $query_vars;
	}

	public function maybe_handle_well_known( \WP $wp ): void {
		$which = $wp->query_vars[ self::WELL_KNOWN_QUERY_VAR ] ?? null;
		if ( null === $which ) {
			return;
		}

		$response = 'server' === $which
			? $this->discovery->authorization_server()
			: $this->discovery->protected_resource();

		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $response->get_data() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON API document.
		exit;
	}
}
