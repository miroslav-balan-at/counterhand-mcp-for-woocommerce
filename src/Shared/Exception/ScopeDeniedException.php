<?php

declare( strict_types=1 );

namespace AgentGateMcp\Shared\Exception;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;

defined( 'ABSPATH' ) || exit;

/**
 * The token lacks a required scope. Message is written for the AI agent
 * so it can explain to the user how to fix it.
 */
final class ScopeDeniedException extends \RuntimeException {

	public function __construct( public readonly ApiScope $required_scope ) {
		parent::__construct( sprintf(
			'This action requires the "%s" scope. Ask the store administrator for a token that grants it.',
			$required_scope->value
		) );
	}
}
