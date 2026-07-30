<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Doubles;

use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Shared\Tool\ToolInterface;

/**
 * A tool with no WooCommerce behind it, so registry and server tests can
 * assert gating without touching the REST layer.
 */
final class StubTool implements ToolInterface {

	public int $calls = 0;

	/** In production this can reach the database, so callers assert on how often it runs. */
	public int $availability_checks = 0;

	/** @var array<string, mixed> */
	public array $received = [];

	public function __construct(
		private readonly string $name,
		private readonly ApiScope $scope,
		private readonly ToolGroup $group,
		private readonly array $schema = [ 'type' => 'object' ],
		private readonly ?\Throwable $throws = null,
		private readonly bool $available = true,
	) {}

	public function name(): string {
		return $this->name;
	}

	public function description(): string {
		return 'Stub tool ' . $this->name . '.';
	}

	public function input_schema(): array {
		return $this->schema;
	}

	public function required_scope(): ApiScope {
		return $this->scope;
	}

	public function group(): ToolGroup {
		return $this->group;
	}

	public function is_available(): bool {
		++$this->availability_checks;

		return $this->available;
	}

	public function execute( array $arguments ): array {
		++$this->calls;
		$this->received = $arguments;

		if ( null !== $this->throws ) {
			throw $this->throws;
		}

		return [ 'ok' => true ];
	}
}
