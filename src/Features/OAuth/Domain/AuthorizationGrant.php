<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The consented grant an authorization code stands for: what the admin
 * approved, for which client, bound to which PKCE challenge.
 */
final readonly class AuthorizationGrant {

	/** @param list<string> $scopes */
	public function __construct(
		public string $client_id,
		public string $redirect_uri,
		public string $code_challenge,
		public array $scopes,
		public int $user_id,
		public string $resource, // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.resourceFound -- "resource" is the RFC 8707 term for the OAuth resource indicator.
	) {}

	/** @return array{client_id: string, redirect_uri: string, code_challenge: string, scopes: list<string>, user_id: int, resource: string} */
	public function to_array(): array {
		return [
			'client_id'      => $this->client_id,
			'redirect_uri'   => $this->redirect_uri,
			'code_challenge' => $this->code_challenge,
			'scopes'         => $this->scopes,
			'user_id'        => $this->user_id,
			'resource'       => $this->resource,
		];
	}

	/** A transient can come back as anything — a malformed payload yields no grant. */
	public static function from_array( mixed $data ): ?self {
		if ( ! is_array( $data ) ) {
			return null;
		}

		$client_id      = $data['client_id'] ?? null;
		$redirect_uri   = $data['redirect_uri'] ?? null;
		$code_challenge = $data['code_challenge'] ?? null;
		$scopes         = $data['scopes'] ?? null;
		$user_id        = $data['user_id'] ?? null;
		$resource       = $data['resource'] ?? null;

		if (
			! is_string( $client_id ) || ! is_string( $redirect_uri ) || ! is_string( $code_challenge )
			|| ! is_array( $scopes ) || ! is_int( $user_id ) || ! is_string( $resource )
		) {
			return null;
		}

		$scope_values = [];
		foreach ( $scopes as $scope ) {
			if ( ! is_string( $scope ) ) {
				return null;
			}
			$scope_values[] = $scope;
		}

		return new self( $client_id, $redirect_uri, $code_challenge, $scope_values, $user_id, $resource );
	}
}
