<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

/**
 * What became of one in-process tool call, in transport-neutral terms.
 *
 * Consumers of the dispatch pipeline read this instead of a wire shape, so how
 * MCP frames results stays McpServer's private business.
 */
final readonly class DispatchOutcome {

	/** @param array<string, mixed> $data */
	private function __construct(
		public DispatchStatus $status,
		public array $data = [],
		public string $message = '',
	) {}

	/** @param array<string, mixed> $data */
	public static function succeeded( array $data ): self {
		return new self( DispatchStatus::Succeeded, $data );
	}

	/** The call never reached a tool: nothing executed, nothing audited. */
	public static function rejected( string $message ): self {
		return new self( DispatchStatus::Rejected, message: $message );
	}

	/** The tool ran (or refused); the message is written for an agent to act on. */
	public static function failed( string $message ): self {
		return new self( DispatchStatus::Failed, message: $message );
	}

	public function is_error(): bool {
		return DispatchStatus::Succeeded !== $this->status;
	}
}
