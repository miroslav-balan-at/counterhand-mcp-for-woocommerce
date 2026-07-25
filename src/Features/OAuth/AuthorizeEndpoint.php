<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OAuth;

use AgentGateMcp\Features\OAuth\View\FlowPage;
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

	private FlowPage $page;

	public function __construct(
		private ClientMetadataResolver $client_resolver,
		private AuthorizationCodeStore $code_store,
	) {
		$this->page = new FlowPage();
	}

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
			$this->page->render_error(
				__( 'Not permitted', 'agentgate-mcp-for-woocommerce' ),
				__( 'Your account cannot authorize AI access to this store. Ask a store administrator to approve the connection.', 'agentgate-mcp-for-woocommerce' ),
				403
			);
		}

		$request = $this->parse_request();
		if ( null === $request ) {
			$this->page->render_error(
				__( 'Incomplete request', 'agentgate-mcp-for-woocommerce' ),
				__( 'This authorization link is missing required details or has expired. Start the connection again from your AI assistant.', 'agentgate-mcp-for-woocommerce' )
			);
		}

		$client = $this->client_resolver->resolve( $request['client_id'] );
		if ( null === $client || ! $client->allows_redirect_uri( $request['redirect_uri'] ) ) {
			// Never redirect to an unvalidated URI — show the error in-page.
			$this->page->render_error(
				__( 'Could not verify the app', 'agentgate-mcp-for-woocommerce' ),
				__( 'We could not confirm the identity of the app requesting access, so nothing was connected. This can happen if the app is misconfigured or the link was altered.', 'agentgate-mcp-for-woocommerce' )
			);
		}

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- fixed verb comparison.
			$this->handle_decision( $request, $client );
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

	private function handle_decision( array $request, ClientMetadata $client ): void {
		check_admin_referer( 'agmcp_authorize' );

		$approved = isset( $_POST['agmcp_approve'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.

		if ( ! $approved ) {
			// Tell the client (spec: error=access_denied + original state) and, if
			// it has no usable callback, show the admin a calm dead-end page.
			$this->redirect_with(
				$request['redirect_uri'],
				[
					'error'             => 'access_denied',
					'error_description' => 'The store administrator denied the request.',
					'state'             => $request['state'],
				],
				FlowPage::STATE_DENIED,
				__( 'Access not granted', 'agentgate-mcp-for-woocommerce' ),
				[ 'client_name' => $client->client_name ]
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

		$granted_labels = array_map(
			static fn ( string $value ): string => ApiScope::from( $value )->label(),
			$granted
		);

		$this->redirect_with(
			$request['redirect_uri'],
			[
				'code'  => $code,
				'state' => $request['state'],
			],
			FlowPage::STATE_CONNECTED,
			__( 'Access approved', 'agentgate-mcp-for-woocommerce' ),
			[
				'client_name'  => $client->client_name,
				'scope_labels' => $granted_labels,
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

		$this->page->render(
			FlowPage::STATE_CONSENT,
			__( 'Authorize AI access', 'agentgate-mcp-for-woocommerce' ),
			[
				'client_name' => $client->client_name,
				'client_host' => (string) wp_parse_url( $client->client_id, PHP_URL_HOST ),
				'scopes'      => $scopes,
				'hidden'      => [
					'client_id'             => $request['client_id'],
					'redirect_uri'          => $request['redirect_uri'],
					'code_challenge'        => $request['code_challenge'],
					'code_challenge_method' => 'S256',
					'state'                 => $request['state'],
					'resource'              => $request['resource'],
					'response_type'         => 'code',
					'scope'                 => implode( ' ', $request['scopes'] ),
				],
			]
		);
	}

	/**
	 * Hands the result back to the client's callback.
	 *
	 * If the callback lives on this very site it is not a real client endpoint
	 * (a stray or placeholder redirect_uri), which would dump the user on a 404.
	 * In that case we render the outcome page instead so the flow always ends on
	 * a designed screen.
	 *
	 * @param array<string, string> $params        Query args for the callback.
	 * @param string                $fallback_state FlowPage::STATE_* to render if there is no usable callback.
	 * @param array<string, mixed>  $fallback_context Context for that page.
	 */
	private function redirect_with(
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
