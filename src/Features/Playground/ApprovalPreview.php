<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

defined( 'ABSPATH' ) || exit;

/**
 * What a parked call would change, read with the tool's own preview, so the person sees
 * the before and after rather than the arguments. A refusal is what the write would hit.
 */
final readonly class ApprovalPreview {

	/** @param list<array{field: string, before: mixed, after: mixed}> $changes */
	public function __construct(
		public string $call_id,
		public array $changes,
		public string $refusal = '',
	) {}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return [
			'id'      => $this->call_id,
			'changes' => $this->changes,
			'refusal' => $this->refusal,
		];
	}
}
