<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Features\Playground\Provider\TokenUsage;

defined( 'ABSPATH' ) || exit;

/**
 * Everything one chat exchange produced.
 *
 * Messages stay in the provider's own wire format and the transcript in the
 * chat UI's payload shape — both exist only to be serialised straight back out.
 */
final readonly class AgentLoopResult {

	/**
	 * @param list<array<string,mixed>> $messages   Conversation in provider format, for the next turn.
	 * @param list<array<string,mixed>> $transcript What the chat UI renders for this exchange.
	 */
	public function __construct(
		public array $messages,
		public array $transcript,
		public TokenUsage $usage,
	) {}
}
