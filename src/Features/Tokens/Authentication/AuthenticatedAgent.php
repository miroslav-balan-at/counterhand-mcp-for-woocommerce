<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Tokens\Authentication;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Features\Tokens\Domain\ApiToken;
use AgentGateMcp\Features\Tokens\Domain\GrantedScopeSet;
use AgentGateMcp\Shared\Exception\ScopeDeniedException;

defined( 'ABSPATH' ) || exit;

/**
 * The authenticated caller of the MCP endpoint. Every tool dispatch passes
 * through require_scope() — the programmatic, fail-closed authorization gate.
 */
final readonly class AuthenticatedAgent {

	public function __construct( public ApiToken $token ) {}

	public function scopes(): GrantedScopeSet {
		return $this->token->scopes;
	}

	/**
	 * @throws ScopeDeniedException When the token does not grant the scope.
	 */
	public function require_scope( ApiScope $scope ): void {
		if ( ! $this->token->scopes->contains( $scope ) ) {
			throw new ScopeDeniedException( $scope ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
		}
	}
}
