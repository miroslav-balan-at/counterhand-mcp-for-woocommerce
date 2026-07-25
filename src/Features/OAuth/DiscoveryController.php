<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OAuth;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\CanonicalUri;

defined( 'ABSPATH' ) || exit;

/**
 * OAuth discovery documents. The plugin is both resource server and
 * authorization server, co-hosted, so it publishes both metadata docs.
 */
final readonly class DiscoveryController {

	public function register_routes(): void {
		register_rest_route(
			'agentgate/v1',
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
			'agentgate/v1',
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
				'token_endpoint'                        => rest_url( 'agentgate/v1/oauth/token' ),
				'scopes_supported'                      => ApiScope::values(),
				'response_types_supported'              => [ 'code' ],
				'grant_types_supported'                 => [ 'authorization_code' ],
				'code_challenge_methods_supported'      => [ 'S256' ],
				'token_endpoint_auth_methods_supported' => [ 'none' ],
			],
			200
		);
	}
}
