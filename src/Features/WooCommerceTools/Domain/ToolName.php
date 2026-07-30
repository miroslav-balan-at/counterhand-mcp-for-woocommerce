<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * A tool's name, checked at the moment a descriptor states it.
 *
 * The 64-character ceiling is not style: ctrh_log.tool_name is VARCHAR(64), so
 * a longer name would be silently truncated in the audit trail — the one record
 * that has to be exact. The character class matches what MCP clients accept
 * without quoting and what a model can reproduce reliably.
 *
 * Constructed from a literal in a descriptor file, so failure is a programmer
 * error surfaced by the catalog test, never something a request can trigger.
 */
final readonly class ToolName {

	private const PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

	private function __construct( public string $value ) {}

	/** @throws \InvalidArgumentException When the name would not survive round-tripping. */
	public static function from( string $value ): self {
		if ( 1 !== preg_match( self::PATTERN, $value ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'"%s" is not a usable tool name: expected up to 64 characters of lower-case letters, digits and underscores, starting with a letter.',
					$value
				)
			);
		}

		return new self( $value );
	}

	public function __toString(): string {
		return $this->value;
	}
}
