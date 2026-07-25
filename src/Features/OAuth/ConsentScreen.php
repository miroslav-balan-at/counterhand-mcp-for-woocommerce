<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OAuth;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the standalone OAuth consent page (its own minimal HTML document,
 * not a wp-admin screen — the browser lands here mid-OAuth-flow).
 *
 * @param list<ApiScope> $scopes
 */
final readonly class ConsentScreen {

	public function render( ClientMetadata $client, array $scopes, array $request ): void {
		$store_name  = get_bloginfo( 'name' );
		$client_name = $client->client_name;
		$hidden      = [
			'client_id'             => $request['client_id'],
			'redirect_uri'          => $request['redirect_uri'],
			'code_challenge'        => $request['code_challenge'],
			'code_challenge_method' => 'S256',
			'state'                 => $request['state'],
			'resource'              => $request['resource'],
			'response_type'         => 'code',
			'scope'                 => implode( ' ', $request['scopes'] ),
		];

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		include __DIR__ . '/views/consent.php';
	}
}
