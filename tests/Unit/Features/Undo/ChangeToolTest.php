<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Undo;

use Counterhand\Features\McpServer\ChangeKind;
use Counterhand\Features\McpServer\ChangePlan;
use Counterhand\Features\McpServer\ReversibleToolInterface;
use Counterhand\Features\McpServer\ToolDispatcherInterface;
use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Undo\ChangeRepositoryInterface;
use Counterhand\Features\Undo\ChangeTool;
use Counterhand\Features\Undo\RecordedChange;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolInterface;
use Counterhand\Tests\Doubles\AgentFactory;
use Counterhand\Tests\Unit\TestCase;

final class ChangeToolTest extends TestCase {

	private function change( ChangeKind $kind = ChangeKind::Updated ): RecordedChange {
		return new RecordedChange( 'abc', 'update_product', new ChangePlan( $kind, [ 'id' => 5 ], [ 'regular_price' ] ), [ 'regular_price' => '10' ], [ 'regular_price' => '99' ], '2026-10-31 10:00:00', null );
	}

	private function repository( ?RecordedChange $change, bool $claimed = true ): object {
		return new class( $change, $claimed ) implements ChangeRepositoryInterface {

			public bool $released = false;

			public function __construct( private ?RecordedChange $change, private bool $claimed ) {}

			public function find( string $key, AuthenticatedAgent $agent ): ?RecordedChange {
				return $this->change;
			}

			public function recent( AuthenticatedAgent $agent, int $limit ): array {
				return null === $this->change ? [] : [ $this->change ];
			}

			public function claim( string $key ): bool {
				return $this->claimed;
			}

			public function release( string $key ): void {
				$this->released = true;
			}
		};
	}

	/** @param array<string, mixed> $now */
	private function original( array $now = [ 'regular_price' => '99' ], bool $restore_fails = false, bool $value_sticks = false ): object {
		return new class( $now, $restore_fails, $value_sticks ) implements ToolInterface, ReversibleToolInterface {
			use ToolStubDefaults;

			/** @var list<array<string, mixed>> */
			public array $restored = [];

			/** @param array<string, mixed> $now */
			public function __construct( private array $now, private bool $fails, private bool $sticks ) {}

			public function name(): string {
				return 'update_product';
			}

			public function is_reversible(): bool {
				return true;
			}

			public function is_undoable(): bool {
				return true;
			}

			public function watched_fields(): array {
				return [];
			}

			public function change_plan( array $arguments ): ChangePlan {
				return new ChangePlan( ChangeKind::Updated, [], [] );
			}

			public function read_state( ChangePlan $plan ): array {
				return $this->now;
			}

			public function with_result( ChangePlan $plan, array $result ): ?ChangePlan {
				return $plan;
			}

			public function restore( ChangePlan $plan, array $before ): void {
				if ( $this->fails ) {
					throw new ToolCallException( 'refused' );
				}

				$this->restored[] = $before;
				$this->now        = $this->sticks ? $this->now : $before;
			}
		};
	}

	private function undo( object $repository, ?object $original ): ChangeTool {
		$tools = $this->createStub( ToolDispatcherInterface::class );
		$tools->method( 'reversible_for' )->willReturn( $original );
		$tools->method( 'restore' )->willReturnCallback( static fn ( $agent, $name, $plan, $before ) => $original?->restore( $plan, $before ) );

		return ChangeTool::both( $repository, $tools )[1];
	}

	private function agent(): AuthenticatedAgent {
		return AgentFactory::with_scopes( [ 'changes:write' ] );
	}

	public function test_an_update_is_put_back(): void {
		$original = $this->original();

		$result = $this->undo( $this->repository( $this->change() ), $original )->execute_as( $this->agent(), [ 'change_id' => 'abc' ] );

		$this->assertTrue( $result['undone'] );
		$this->assertSame( [ [ 'regular_price' => '10' ] ], $original->restored );
	}

	/** The repository answers null for another connection's change, so this is the IDOR gate. */
	public function test_a_change_this_connection_did_not_make_is_not_found(): void {
		$original = $this->original();

		try {
			$this->undo( $this->repository( null ), $original )->execute_as( $this->agent(), [ 'change_id' => 'abc' ] );
			$this->fail( 'expected a refusal' );
		} catch ( ToolCallException ) {
			$this->assertSame( [], $original->restored );
		}
	}

	/** Restoring would silently throw away whatever came after. */
	public function test_it_refuses_when_the_fields_have_changed_again(): void {
		$original = $this->original( [ 'regular_price' => '50' ] );

		try {
			$this->undo( $this->repository( $this->change() ), $original )->execute_as( $this->agent(), [ 'change_id' => 'abc' ] );
			$this->fail( 'expected a refusal' );
		} catch ( ToolCallException $exception ) {
			$this->assertStringContainsString( 'changed again', $exception->getMessage() );
			$this->assertSame( [], $original->restored );
		}
	}

	public function test_it_refuses_when_the_connection_can_no_longer_use_the_tool(): void {
		$this->expectException( ToolCallException::class );
		$this->expectExceptionMessage( 'can no longer use' );

		$this->undo( $this->repository( $this->change() ), null )->execute_as( $this->agent(), [ 'change_id' => 'abc' ] );
	}

	/** A route can answer success and leave a value alone. */
	public function test_a_field_that_did_not_go_back_is_reported_not_called_done(): void {
		try {
			$this->undo( $this->repository( $this->change() ), $this->original( value_sticks: true ) )->execute_as( $this->agent(), [ 'change_id' => 'abc' ] );
			$this->fail( 'expected a refusal' );
		} catch ( ToolCallException $exception ) {
			$this->assertStringContainsString( 'partly undone', $exception->getMessage() );
			$this->assertStringContainsString( 'regular_price', $exception->getMessage() );
		}
	}

	public function test_a_change_already_undone_says_so_before_comparing_anything(): void {
		$change = new RecordedChange( 'abc', 'update_product', new ChangePlan( ChangeKind::Updated, [ 'id' => 5 ], [ 'regular_price' ] ), [ 'regular_price' => '10' ], [ 'regular_price' => '99' ], '2026-10-31 10:00:00', '2026-10-31 11:00:00' );

		$this->expectExceptionMessage( 'already been undone' );
		$this->undo( $this->repository( $change ), $this->original( [ 'regular_price' => '10' ] ) )->execute_as( $this->agent(), [ 'change_id' => 'abc' ] );
	}

	public function test_a_second_undo_changes_nothing(): void {
		$original = $this->original();

		try {
			$this->undo( $this->repository( $this->change(), claimed: false ), $original )->execute_as( $this->agent(), [ 'change_id' => 'abc' ] );
			$this->fail( 'expected a refusal' );
		} catch ( ToolCallException $exception ) {
			$this->assertStringContainsString( 'already been undone', $exception->getMessage() );
			$this->assertSame( [], $original->restored );
		}
	}

	public function test_an_undo_the_store_refused_can_be_tried_again(): void {
		$repository = $this->repository( $this->change() );

		try {
			$this->undo( $repository, $this->original( restore_fails: true ) )->execute_as( $this->agent(), [ 'change_id' => 'abc' ] );
			$this->fail( 'expected a refusal' );
		} catch ( ToolCallException ) {
			$this->assertTrue( $repository->released );
		}
	}

	public function test_only_undoing_needs_confirmation(): void {
		[ $list, $undo ] = ChangeTool::both( $this->repository( null ), $this->createStub( ToolDispatcherInterface::class ) );

		$this->assertFalse( $list->requires_confirmation() );
		$this->assertTrue( $undo->requires_confirmation() );
	}
}

trait ToolStubDefaults {

	public function description(): string {
		return '';
	}

	public function input_schema(): array {
		return [];
	}

	public function required_scope(): \Counterhand\Features\Tokens\Domain\ScopeInterface {
		return \Counterhand\Features\Tokens\Domain\ApiScope::ProductsWrite;
	}

	public function group(): \Counterhand\Features\Tokens\Domain\ScopeGroupInterface {
		return \Counterhand\Shared\Tool\ToolGroup::Products;
	}

	public function is_available(): bool {
		return true;
	}

	public function requires_confirmation(): bool {
		return false;
	}

	public function is_read_only(): bool {
		return false;
	}

	public function is_destructive(): bool {
		return false;
	}

	public function is_idempotent(): bool {
		return true;
	}

	public function is_open_world(): bool {
		return false;
	}

	public function execute( array $arguments ): array {
		return [];
	}
}
