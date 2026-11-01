<?php

declare( strict_types=1 );

namespace Counterhand\Features\Undo;

use Counterhand\Features\McpServer\ChangePlan;

defined( 'ABSPATH' ) || exit;

final readonly class RecordedChange {

	/**
	 * @param array<string, mixed> $before
	 * @param array<string, mixed> $after
	 */
	public function __construct(
		public string $key,
		public string $tool_name,
		public ChangePlan $plan,
		public array $before,
		public array $after,
		public string $created_at,
		public ?string $undone_at,
	) {}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return [
			'change_id' => $this->key,
			'tool'      => $this->tool_name,
			'action'    => $this->plan->kind->value,
			'record'    => $this->plan->path,
			'fields'    => $this->plan->fields,
			'before'    => $this->before,
			'made_at'   => $this->created_at,
			'undone_at' => $this->undone_at,
		];
	}
}
