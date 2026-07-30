<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * One area as the Chat tab's picker needs to show it.
 *
 * The picker used to render a bare checkbox per group, which made a tick look
 * like a promise. It is not: the store's own Settings decide whether the area is
 * exposed at all, and a tick on an area switched off there changes nothing. That
 * mismatch is invisible unless something carries both facts to the view.
 */
final readonly class ChatArea {

	private function __construct(
		public ToolGroup $group,
		public bool $is_selected,
		public bool $store_allows_read,
		public bool $store_allows_write,
		public int $tool_count,
	) {}

	/** @param list<ToolGroup> $selected */
	public static function of( ToolGroup $group, array $selected, ChatToolPolicy $policy, int $tool_count ): self {
		return new self(
			$group,
			in_array( $group, $selected, true ),
			$policy->allows_read( $group ),
			$policy->allows_write( $group ),
			$tool_count
		);
	}

	/** Ticking this changes nothing until the store exposes the area. */
	public function is_overruled_by_store(): bool {
		return ! $this->store_allows_read;
	}

	/**
	 * Selected, exposed for reading, but the write half is still withheld.
	 *
	 * Worth saying out loud: an agent that can read orders but not touch them
	 * looks broken to someone who ticked "Orders" expecting both.
	 */
	public function is_read_only_by_store(): bool {
		return $this->is_selected
			&& $this->store_allows_read
			&& $this->group->has_write()
			&& ! $this->store_allows_write;
	}
}
