<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Doubles;

use Counterhand\Features\McpServer\OpenArgumentsToolInterface;
use Counterhand\Features\Tokens\Domain\ScopeGroupInterface;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Shared\Tool\ToolInterface;

/**
 * A StubTool that, like an open WooCommerce write, accepts more than it publishes.
 */
final class OpenArgumentsStubTool implements ToolInterface, OpenArgumentsToolInterface {

	public int $accepted_schema_reads = 0;

	/** @param array<string, mixed> $accepted */
	public function __construct(
		public readonly StubTool $inner,
		private readonly array $accepted,
	) {}

	public function accepted_arguments_schema(): array {
		++$this->accepted_schema_reads;

		return $this->accepted;
	}

	public function name(): string {
		return $this->inner->name();
	}

	public function description(): string {
		return $this->inner->description();
	}

	public function input_schema(): array {
		return $this->inner->input_schema();
	}

	public function required_scope(): ScopeInterface {
		return $this->inner->required_scope();
	}

	public function group(): ScopeGroupInterface {
		return $this->inner->group();
	}

	public function is_open_world(): bool {
		return $this->inner->is_open_world();
	}

	public function requires_confirmation(): bool {
		return $this->inner->requires_confirmation();
	}

	public function is_read_only(): bool {
		return $this->inner->is_read_only();
	}

	public function is_destructive(): bool {
		return $this->inner->is_destructive();
	}

	public function is_idempotent(): bool {
		return $this->inner->is_idempotent();
	}

	public function is_available(): bool {
		return $this->inner->is_available();
	}

	public function execute( array $arguments ): array {
		return $this->inner->execute( $arguments );
	}
}
