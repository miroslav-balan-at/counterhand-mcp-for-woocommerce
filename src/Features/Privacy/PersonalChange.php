<?php

declare( strict_types=1 );

namespace Counterhand\Features\Privacy;

defined( 'ABSPATH' ) || exit;

final readonly class PersonalChange {

	/** @param list<string> $fields */
	public function __construct(
		public string $key,
		public string $tool_name,
		public string $created_at,
		public array $fields,
	) {}
}
