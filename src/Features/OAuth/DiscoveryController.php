<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth;

use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Shared\CanonicalUri;

defined( 'ABSPATH' ) || exit;

/**
 * OAuth discovery documents. The plugin is both resource server and
 * authorization server, co-hosted, so it publishes both metadata docs.
 */
final readonly class DiscoveryController {

	public function register_routes(): void {
		register_rest_route(
			'counterhand/v1',
			'/oauth-protected-resource',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'protected_resource' ],
					'permission_callback' => '__return_true',
				],
			]
		);

		register_rest_route(
			'counterhand/v1',
			'/oauth-authorization-server',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'authorization_server' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/** RFC 9728 — points clients at the authorization server. */
	public function protected_resource(): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'resource'                 => CanonicalUri::mcp(),
				'authorization_servers'    => [ home_url() ],
				'scopes_supported'         => ApiScope::values(),
				'bearer_methods_supported' => [ 'header' ],
			],
			200
		);
	}

	/** RFC 8414 — the authorization server's capabilities. */
	public function authorization_server(): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'issuer'                                => home_url(),
				'authorization_endpoint'                => home_url( '/mcp-authorize' ),
				'token_endpoint'                        => rest_url( 'counterhand/v1/oauth/token' ),
				'scopes_supported'                      => ApiScope::values(),
				'response_types_supported'              => [ 'code' ],
				'grant_types_supported'                 => [ 'authorization_code' ],
				'code_challenge_methods_supported'      => [ 'S256' ],
				'token_endpoint_auth_methods_supported' => [ 'none' ],
				/*
				 * How clients discover that this server identifies them by their
				 * Client ID Metadata Document instead of Dynamic Client
				 * Registration (draft-ietf-oauth-client-id-metadata-document,
				 * adopted by MCP as SEP-991).
				 *
				 * Without this flag a client sees an authorization server with
				 * no registration_endpoint and no advertised alternative, and
				 * refuses to connect — Claude Code reports "Incompatible auth
				 * server: does not support dynamic client registration". CIMD is
				 * this plugin's whole authorization model, so omitting the flag
				 * made it undiscoverable.
				 */
				'client_id_metadata_document_supported' => true,
			],
			200
		);
	}
}
