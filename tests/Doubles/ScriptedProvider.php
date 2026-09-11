<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Doubles;

use Counterhand\Features\Playground\Provider\ProviderConfig;
use Counterhand\Features\Playground\Provider\ProviderInterface;
use Counterhand\Features\Playground\Provider\ProviderTurn;

/**
 * A model that answers from a script, so the loop around it can be observed:
 * what it was sent, and what it did with each answer.
 */
final class ScriptedProvider implements ProviderInterface {

	/** @var list<list<array<string,mixed>>> The messages of every complete() call. */
	public array $seen = [];

	/** @param list<ProviderTurn> $turns */
	public function __construct( private array $turns ) {}

	public function complete( array $messages, array $tools, ProviderConfig $config ): ProviderTurn {
		$this->seen[] = $messages;

		return array_shift( $this->turns ) ?? new ProviderTurn( 'Nothing left to say.', [], false );
	}

	public function id(): string {
		return 'scripted';
	}

	public function label(): string {
		return 'Scripted';
	}

	public function default_models(): array {
		return [];
	}

	public function needs_base_url(): bool {
		return false;
	}

	public function default_base_url(): string {
		return '';
	}

	public function needs_key(): bool {
		return false;
	}

	public function is_user_configured(): bool {
		return true;
	}

	public function is_ready( ProviderConfig $config ): bool {
		return true;
	}

	public function key_url(): string {
		return '';
	}

	public function test( ProviderConfig $config ): void {}

	public function describe_tool( string $name, string $description, array $input_schema ): array {
		return [ 'name' => $name ];
	}

	public function max_eager_tools(): ?int {
		return null;
	}

	public function with_tool_search( array $tools ): array {
		return $tools;
	}

	public function assistant_message( ProviderTurn $turn ): array {
		return [
			'role'  => 'assistant',
			'calls' => array_map( static fn ( $call ): string => $call->id, $turn->tool_calls ),
		];
	}

	public function tool_result_messages( array $results ): array {
		return array_map(
			static fn ( $result ): array => [
				'role'     => 'tool',
				'id'       => $result->id,
				'output'   => $result->output,
				'is_error' => $result->is_error,
			],
			$results
		);
	}

	public function user_message( string $text ): array {
		return [
			'role' => 'user',
			'text' => $text,
		];
	}
}
