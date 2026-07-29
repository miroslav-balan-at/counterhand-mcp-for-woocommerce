<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OAuth\View;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * One row of the consent screen: a tool group, and whichever of its two scopes
 * the client actually asked for.
 *
 * Grouping the two axes into one row is what keeps the screen readable as the
 * catalogue grows. A flat scope list reads fine at six entries and becomes an
 * unskimmable wall at thirty, which is the state in which people stop reading
 * and just approve.
 */
final readonly class ConsentGroup {

	private function __construct(
		public ToolGroup $group,
		public ?ApiScope $read,
		public ?ApiScope $write,
	) {}

	/**
	 * Null when the client asked for neither of this group's scopes — the row
	 * is then absent rather than rendered empty.
	 *
	 * @param  list<ApiScope> $offered
	 */
	public static function from( ToolGroup $group, array $offered ): ?self {
		$write = $group->write_scope();

		$read  = in_array( $group->read_scope(), $offered, true ) ? $group->read_scope() : null;
		$write = null !== $write && in_array( $write, $offered, true ) ? $write : null;

		if ( null === $read && null === $write ) {
			return null;
		}

		return new self( $group, $read, $write );
	}

	/**
	 * Whether the boxes on this row start ticked.
	 *
	 * Advanced groups never do, even when the client explicitly requested them:
	 * they change how the store charges money or run maintenance routines, so
	 * granting one should take a deliberate click rather than the absence of an
	 * untick.
	 */
	public function pre_checked(): bool {
		return ! $this->group->section()->is_advanced();
	}

	public function has_write(): bool {
		return null !== $this->write;
	}
}
