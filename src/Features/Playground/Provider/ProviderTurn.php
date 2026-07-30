<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * The normalised result of one model turn.
 */
final readonly class ProviderTurn {

	/**
	 * @param string              $text        Assistant prose, if any.
	 * @param list<ToolCall>      $tool_calls  Tools the model wants run.
	 * @param bool                $wants_tools Whether the loop should continue.
	 * @param array<string,mixed> $raw         Provider payload, for assistant_message().
	 * @param TokenUsage          $usage       Token usage, best effort.
	 */
	public function __construct(
		public string $text,
		public array $tool_calls,
		public bool $wants_tools,
		public array $raw = [],
		public TokenUsage $usage = new TokenUsage(),
	) {}
}
