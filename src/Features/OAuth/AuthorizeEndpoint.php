<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OAuth;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Features\Tokens\Domain\GrantedScopeSet;
use AgentGateMcp\Shared\CanonicalUri;

defined( 'ABSPATH' ) || exit;

/**
 * OAuth 2.1 authorization endpoint + consent screen.
 *
 * GET  /mcp-authorize?... → render the WordPress consent screen (login-gated).
 * POST (same URL)         → admin approved: mint a single-use code, redirect back.
 *
 * A front-end route (not REST) because it renders HTML and relies on
 * auth_redirect() to bounce anonymous browsers through wp-login.
 */
final readonly class AuthorizeEndpoint {

	public const QUERY_VAR = 'agmcp_authorize';

	public function __construct(
		private ClientMetadataResolver $client_resolver,
		private AuthorizationCodeStore $code_store,
	) {}

	public static function register_rewrite(): void {
		add_rewrite_rule( '^mcp-authorize/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public function add_query_var( array $query_vars ): array {
		$query_vars[] = self::QUERY_VAR;

		return $query_vars;
	}

	public function maybe_handle( \WP $wp ): void {
		if ( '1' !== ( $wp->query_vars[ self::QUERY_VAR ] ?? null ) ) {
			return;
		}

		// Consent must be given by a store admin — bounce anonymous browsers to login.
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to authorize AI access to this store.', 'agentgate-mcp-for-woocommerce' ), 403 );
		}

		$request = $this->parse_request();
		if ( null === $request ) {
			wp_die( esc_html__( 'Invalid authorization request.', 'agentgate-mcp-for-woocommerce' ), 400 );
		}

		$client = $this->client_resolver->resolve( $request['client_id'] );
		if ( null === $client || ! $client->allows_redirect_uri( $request['redirect_uri'] ) ) {
			// Never redirect to an unvalidated URI — show the error in-page.
			wp_die( esc_html__( 'The client could not be verified or its redirect URI is not allowed.', 'agentgate-mcp-for-woocommerce' ), 400 );
		}

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- fixed verb comparison.
			$this->handle_decision( $request );
			return;
		}

		$this->render_consent( $request, $client );
		exit;
	}

	/** @return array{client_id: string, redirect_uri: string, code_challenge: string, state: string, resource: string, scopes: list<string>}|null */
	private function parse_request(): ?array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth authorize params, not a WP form; CSRF handled by state + POST nonce on decision.
		$client_id      = esc_url_raw( wp_unslash( $_REQUEST['client_id'] ?? '' ) );
		$redirect_uri   = esc_url_raw( wp_unslash( $_REQUEST['redirect_uri'] ?? '' ) );
		$response_type  = sanitize_text_field( wp_unslash( $_REQUEST['response_type'] ?? '' ) );
		$code_challenge = sanitize_text_field( wp_unslash( $_REQUEST['code_challenge'] ?? '' ) );
		$method         = sanitize_text_field( wp_unslash( $_REQUEST['code_challenge_method'] ?? '' ) );
		$state          = sanitize_text_field( wp_unslash( $_REQUEST['state'] ?? '' ) );
		$resource       = esc_url_raw( wp_unslash( $_REQUEST['resource'] ?? '' ) );
		$scope          = sanitize_text_field( wp_unslash( $_REQUEST['scope'] ?? '' ) );
		// phpcs:enable

		if ( 'code' !== $response_type || 'S256' !== $method || '' === $client_id || '' === $redirect_uri ) {
			return null;
		}

		if ( ! Pkce::is_valid_challenge( $code_challenge ) ) {
			return null;
		}

		// RFC 8707: the resource must be our canonical MCP URI.
		if ( '' === $resource || ! CanonicalUri::matches( $resource, CanonicalUri::mcp() ) ) {
			return null;
		}

		$requested = GrantedScopeSet::from_csv( str_replace( ' ', ',', $scope ) );

		return [
			'client_id'      => $client_id,
			'redirect_uri'   => $redirect_uri,
			'code_challenge' => $code_challenge,
			'state'          => $state,
			'resource'       => CanonicalUri::mcp(),
			'scopes'         => array_map( static fn ( ApiScope $scope ): string => $scope->value, $requested->all() ),
		];
	}

	private function handle_decision( array $request ): void {
		check_admin_referer( 'agmcp_authorize' );

		$approved = isset( $_POST['agmcp_approve'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.

		if ( ! $approved ) {
			$this->redirect_with(
				$request['redirect_uri'],
				[
					'error'             => 'access_denied',
					'error_description' => 'The store administrator denied the request.',
					'state'             => $request['state'],
				]
			);
		}

		// Admin may narrow the requested scopes on the consent screen.
		$granted = array_values(
			array_intersect(
				$request['scopes'],
				array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['agmcp_scopes'] ?? [] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
			)
		);

		$code = $this->code_store->mint(
			$request['client_id'],
			$request['redirect_uri'],
			$request['code_challenge'],
			$granted,
			get_current_user_id(),
			$request['resource']
		);

		$this->redirect_with(
			$request['redirect_uri'],
			[
				'code'  => $code,
				'state' => $request['state'],
			]
		);
	}

	private function render_consent( array $request, ClientMetadata $client ): void {
		$scopes = array_values(
			array_filter(
				array_map( static fn ( string $value ): ?ApiScope => ApiScope::tryFrom( $value ), $request['scopes'] )
			)
		);

		// Fall back to all scopes if the client requested none explicitly.
		if ( [] === $scopes ) {
			$scopes = ApiScope::cases();
		}

		( new ConsentScreen() )->render( $client, $scopes, $request );
	}

	private function redirect_with( string $redirect_uri, array $params ): never {
		wp_redirect( add_query_arg( array_map( 'rawurlencode', array_filter( $params, static fn ( $value ): bool => '' !== $value ) ), $redirect_uri ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- OAuth redirect to a CIMD-validated client URI, not an internal page.
		exit;
	}
}
