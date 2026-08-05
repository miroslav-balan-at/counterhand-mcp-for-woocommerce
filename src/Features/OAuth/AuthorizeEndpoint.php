<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth;

use Counterhand\Features\OAuth\Domain\AuthorizationGrant;
use Counterhand\Features\OAuth\Domain\AuthorizationRequest;
use Counterhand\Features\OAuth\View\ConsentScopes;
use Counterhand\Features\OAuth\View\FlowPage;
use Counterhand\Features\Settings\AdminScreen;
use Counterhand\Features\Settings\PublishedScopes;
use Counterhand\Features\Tokens\Domain\ApiScope;

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

	public const QUERY_VAR = 'counterhand_authorize';

	private FlowPage $page;
	private AuthorizationRequestParser $parser;
	private CallbackRedirector $redirector;

	public function __construct(
		private ClientMetadataResolver $client_resolver,
		private AuthorizationCodeStore $code_store,
		private PublishedScopes $published,
	) {
		$this->page       = new FlowPage();
		$this->parser     = new AuthorizationRequestParser();
		$this->redirector = new CallbackRedirector( $this->page );
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
				__( 'Not permitted', 'counterhand-mcp-for-woocommerce' ),
				__( 'Your account cannot authorize AI access to this store. Ask a store administrator to approve the connection.', 'counterhand-mcp-for-woocommerce' ),
				403
			);
		}

		$request = $this->parser->parse();
		if ( null === $request ) {
			$this->page->render_error(
				__( 'Incomplete request', 'counterhand-mcp-for-woocommerce' ),
				__( 'This authorization link is missing required details or has expired. Start the connection again from your AI assistant.', 'counterhand-mcp-for-woocommerce' )
			);
		}

		$client = $this->client_resolver->resolve( $request->client_id );
		if ( null === $client || ! $client->allows_redirect_uri( $request->redirect_uri ) ) {
			// Never redirect to an unvalidated URI — show the error in-page.
			$this->page->render_error(
				__( 'Could not verify the app', 'counterhand-mcp-for-woocommerce' ),
				__( 'We could not confirm the identity of the app requesting access, so nothing was connected. This can happen if the app is misconfigured or the link was altered.', 'counterhand-mcp-for-woocommerce' )
			);
		}

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- fixed verb comparison.
			$this->handle_decision( $request, $client );
			return;
		}

		$this->render_consent( $request, $client );
		exit;
	}

	private function handle_decision( AuthorizationRequest $request, ClientMetadata $client ): void {
		check_admin_referer( 'counterhand_authorize' );

		$approved = isset( $_POST['counterhand_approve'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.

		if ( ! $approved ) {
			// Tell the client (spec: error=access_denied + original state) and, if
			// it has no usable callback, show the admin a calm dead-end page.
			$this->redirector->redirect_with(
				$request->redirect_uri,
				[
					'error'             => 'access_denied',
					'error_description' => 'The store administrator denied the request.',
					'state'             => $request->state,
				],
				FlowPage::STATE_DENIED,
				__( 'Access not granted', 'counterhand-mcp-for-woocommerce' ),
				[ 'client_name' => $client->client_name ]
			);
		}

		/*
		 * Three-way intersection: what the request carried, what the admin
		 * ticked, and what the store publishes. The first two arrive from the
		 * browser and are client-writable, so without the third a crafted POST
		 * could mint a grant for a group the store has switched off. Settings
		 * changing between render and approval collapses to the same case.
		 */
		$granted = array_values(
			array_intersect(
				$request->scopes,
				array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['counterhand_scopes'] ?? [] ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
				$this->published->values()
			)
		);

		$code = $this->code_store->mint(
			new AuthorizationGrant(
				client_id: $request->client_id,
				redirect_uri: $request->redirect_uri,
				code_challenge: $request->code_challenge,
				scopes: $granted,
				user_id: get_current_user_id(),
				resource: $request->resource,
			)
		);

		$granted_labels = array_map(
			static fn ( string $value ): string => ApiScope::from( $value )->label(),
			$granted
		);

		$this->redirector->redirect_with(
			$request->redirect_uri,
			[
				'code'  => $code,
				'state' => $request->state,
			],
			FlowPage::STATE_CONNECTED,
			__( 'Access approved', 'counterhand-mcp-for-woocommerce' ),
			[
				'client_name'  => $client->client_name,
				'scope_labels' => $granted_labels,
			]
		);
	}

	private function render_consent( AuthorizationRequest $request, ClientMetadata $client ): void {
		// The screen gets everything the client asked for — withheld scopes
		// render as disabled rows — while the replay field below carries only
		// what the store can actually grant.
		$requested = $request->offered_scopes();
		$scopes    = $this->published->grantable( $requested );

		$this->page->render(
			FlowPage::STATE_CONSENT,
			__( 'Authorize AI access', 'counterhand-mcp-for-woocommerce' ),
			[
				'client_name'  => $client->client_name,
				'client_host'  => (string) wp_parse_url( $client->client_id, PHP_URL_HOST ),
				'scopes'       => ConsentScopes::from( $requested, $this->published ),
				'settings_url' => AdminScreen::Settings->url(),
				'hidden'       => [
					'client_id'             => $request->client_id,
					'redirect_uri'          => $request->redirect_uri,
					'code_challenge'        => $request->code_challenge,
					'code_challenge_method' => 'S256',
					'state'                 => $request->state,
					'resource'              => $request->resource,
					'response_type'         => 'code',
					// The offered set, not the requested one. handle_decision()
					// intersects the admin's ticks with whatever this field
					// replays, so a client that named no scopes would otherwise
					// walk away with a token granting nothing — the screen would
					// show a full list of ticked boxes and mint an empty grant.
					'scope'                 => implode( ' ', array_map( static fn ( ApiScope $scope ): string => $scope->value, $scopes ) ),
				],
			]
		);
	}
}
