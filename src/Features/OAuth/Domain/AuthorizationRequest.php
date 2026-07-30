<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth\Domain;

use Counterhand\Features\Tokens\Domain\ApiScope;

defined( 'ABSPATH' ) || exit;

/**
 * A validated OAuth 2.1 authorization request: everything the consent screen
 * and the decision handler need, already checked for PKCE and RFC 8707.
 */
final readonly class AuthorizationRequest {

	/** @param list<string> $scopes */
	public function __construct(
		public string $client_id,
		public string $redirect_uri,
		public string $code_challenge,
		public string $state,
		public string $resource, // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.resourceFound -- "resource" is the RFC 8707 term for the OAuth resource indicator.
		public array $scopes,
	) {}

	/**
	 * The scopes the consent screen puts in front of the admin.
	 *
	 * @return list<ApiScope>
	 */
	public function offered_scopes(): array {
		$scopes = array_values(
			array_filter(
				array_map( static fn ( string $value ): ?ApiScope => ApiScope::tryFrom( $value ), $this->scopes )
			)
		);

		// A client that named no scopes is offered a read-only, non-advanced set
		// rather than the whole catalogue — see ApiScope::conservative_default().
		return [] === $scopes ? ApiScope::conservative_default() : $scopes;
	}
}
