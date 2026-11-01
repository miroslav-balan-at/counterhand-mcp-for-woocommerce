<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playbooks;

use Counterhand\Features\McpServer\PromptDefinition;

defined( 'ABSPATH' ) || exit;

final readonly class Playbook {

	/** @param list<string> $tools Every tool it needs, by name. */
	public function __construct(
		public PromptDefinition $prompt,
		public array $tools,
	) {}
}
