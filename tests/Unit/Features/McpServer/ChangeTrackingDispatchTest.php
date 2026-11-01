<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\McpServer;

use Brain\Monkey\Functions;
use Counterhand\Features\McpServer\ChangeJournalInterface;
use Counterhand\Features\McpServer\ChangeKind;
use Counterhand\Features\McpServer\ChangePlan;
use Counterhand\Features\McpServer\ReversibleToolInterface;
use Counterhand\Features\McpServer\ToolDispatcher;
use Counterhand\Features\McpServer\ToolRegistry;
use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Features\Tokens\Domain\ScopeGroupInterface;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Shared\Tool\ToolInterface;
use Counterhand\Tests\Doubles\AgentFactory;
use Counterhand\Tests\Doubles\StubTool;
use Counterhand\Tests\Unit\TestCase;

/** What the pipeline does around a write that can be previewed and put back. */
final class ChangeTrackingDispatchTest extends TestCase {

	private const WITH_HISTORY = [ 'products:write', 'changes:write' ];

	/** @param array<string, mixed> $state What the record holds. */
	private function tool( array $state = [ 'regular_price' => '10' ], bool $requires_confirmation = false, ?string $refusal = null ): object {
		return new class( $state, $requires_confirmation, $refusal ) implements ToolInterface, ReversibleToolInterface {

			/** @var list<array<string, mixed>> */
			public array $executed = [];

			public int $reads = 0;

			public bool $read_fails = false;

			/** @param array<string, mixed> $state */
			public function __construct( public array $state, private bool $confirm, private ?string $refusal ) {}

			public function name(): string {
				return 'a_write';
			}

			public function description(): string {
				return '';
			}

			public function input_schema(): array {
				return [
					'type'                 => 'object',
					'properties'           => [
						'id'            => [ 'type' => 'integer' ],
						'regular_price' => [ 'type' => 'string' ],
					],
					'additionalProperties' => false,
				];
			}

			public function required_scope(): ScopeInterface {
				return ApiScope::ProductsWrite;
			}

			public function group(): ScopeGroupInterface {
				return ToolGroup::Products;
			}

			public function is_available(): bool {
				return true;
			}

			public function requires_confirmation(): bool {
				return $this->confirm;
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
				$this->executed[] = $arguments;
				$this->state      = [
					...$this->state,
					'regular_price' => $arguments['regular_price'] ?? '',
				];

				if ( array_key_exists( 'sale_price', $this->state ) ) {
					$this->state['sale_price'] = '';
				}

				return [ 'product' => [ 'id' => 5 ] ];
			}

			public function is_reversible(): bool {
				return true;
			}

			public function is_undoable(): bool {
				return true;
			}

			public function watched_fields(): array {
				return array_keys( $this->state );
			}

			public function change_plan( array $arguments ): ChangePlan {
				if ( null !== $this->refusal ) {
					throw new ToolCallException( $this->refusal );
				}

				return new ChangePlan( ChangeKind::Updated, [ 'id' => $arguments['id'] ?? 0 ], [ 'regular_price' ] );
			}

			public function read_state( ChangePlan $plan ): array {
				++$this->reads;

				if ( $this->read_fails ) {
					throw new ToolCallException( 'gone' );
				}

				return array_intersect_key( $this->state, array_flip( $plan->fields ) );
			}

			public function with_result( ChangePlan $plan, array $result ): ?ChangePlan {
				return $plan;
			}

			public function restore( ChangePlan $plan, array $before ): void {}
		};
	}

	private function journal(): object {
		return new class() implements ChangeJournalInterface {

			/** @var list<array<string, mixed>> */
			public array $recorded = [];

			public ?string $key = 'change-1';

			public function record( AuthenticatedAgent $agent, string $tool_name, ChangePlan $plan, array $before, array $after ): ?string {
				$this->recorded[] = compact( 'tool_name', 'before', 'after', 'plan' );

				return $this->key;
			}
		};
	}

	private function dispatcher( object $tool, ?object $journal = null, bool $history_switched_on = true ): ToolDispatcher {
		Functions\when( 'get_option' )->justReturn(
			[
				'products_read'  => true,
				'products_write' => true,
				'changes_write'  => $history_switched_on,
			]
		);
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'rest_sanitize_value_from_schema' )->returnArg( 1 );
		Functions\when( 'rest_validate_value_from_schema' )->justReturn( true );

		$registry = new ToolRegistry( new PluginSettings() );
		$registry->add( $tool );
		$registry->add( new StubTool( 'undo_change', ApiScope::ChangesWrite, ToolGroup::Changes, [ 'type' => 'object' ] ) );

		return new ToolDispatcher( $registry, $journal );
	}

	public function test_a_preview_shows_the_difference_and_saves_nothing(): void {
		$tool    = $this->tool();
		$journal = $this->journal();

		$outcome = $this->dispatcher( $tool, $journal )->dispatch(
			'a_write',
			[
				'id'            => 5,
				'regular_price' => '99',
				'preview'       => true,
			],
			AgentFactory::with_scopes( self::WITH_HISTORY )
		);

		$this->assertFalse( $outcome->is_error() );
		$this->assertFalse( $outcome->data['saved'] );
		$this->assertSame(
			[
				[
					'field'  => 'regular_price',
					'before' => '10',
					'after'  => '99',
				],
			],
			$outcome->data['changes']
		);
		$this->assertSame( [], $tool->executed );
		$this->assertSame( [], $journal->recorded );
	}

	/** Nothing has changed yet, so there is nothing for the person to agree to. */
	public function test_a_preview_needs_no_confirmation_but_the_write_still_does(): void {
		$tool       = $this->tool( requires_confirmation: true );
		$dispatcher = $this->dispatcher( $tool );
		$agent      = AgentFactory::with_scopes( [ 'products:write' ] );

		$this->assertFalse(
			$dispatcher->dispatch(
				'a_write',
				[
					'id'            => 5,
					'regular_price' => '99',
					'preview'       => true,
				],
				$agent
			)->is_error()
		);
		$this->assertTrue(
			$dispatcher->dispatch(
				'a_write',
				[
					'id'            => 5,
					'regular_price' => '99',
				],
				$agent
			)->is_error()
		);
		$this->assertSame( [], $tool->executed );
	}

	public function test_a_preview_false_is_a_real_write_and_the_tool_never_sees_it(): void {
		$tool = $this->tool();

		$this->dispatcher( $tool )->dispatch(
			'a_write',
			[
				'id'            => 5,
				'regular_price' => '99',
				'preview'       => false,
			],
			AgentFactory::with_scopes( [ 'products:write' ] )
		);

		$this->assertSame(
			[
				[
					'id'            => 5,
					'regular_price' => '99',
				],
			],
			$tool->executed
		);
	}

	/** Otherwise a preview would promise a change the real call refuses. */
	public function test_a_preview_is_refused_by_the_rules_the_write_would_be(): void {
		$outcome = $this->dispatcher( $this->tool( refusal: 'not allowed' ) )->dispatch(
			'a_write',
			[
				'id'      => 5,
				'preview' => true,
			],
			AgentFactory::with_scopes( [ 'products:write' ] )
		);

		$this->assertTrue( $outcome->is_error() );
		$this->assertSame( 'not allowed', $outcome->message );
	}

	public function test_a_connection_with_history_gets_a_change_id_and_before_and_after_are_kept(): void {
		$journal = $this->journal();

		$outcome = $this->dispatcher( $this->tool(), $journal )->dispatch(
			'a_write',
			[
				'id'            => 5,
				'regular_price' => '99',
			],
			AgentFactory::with_scopes( self::WITH_HISTORY )
		);

		$this->assertSame( 'change-1', $outcome->data['change_id'] );
		$this->assertSame( '10', $journal->recorded[0]['before']['regular_price'] );
		$this->assertSame( '99', $journal->recorded[0]['after']['regular_price'] );
	}

	/** A record holds store data, and a connection nobody gave history to pays no extra reads. */
	public function test_a_connection_without_history_records_nothing_and_reads_nothing(): void {
		$tool    = $this->tool();
		$journal = $this->journal();

		$outcome = $this->dispatcher( $tool, $journal )->dispatch(
			'a_write',
			[
				'id'            => 5,
				'regular_price' => '99',
			],
			AgentFactory::with_scopes( [ 'products:write' ] )
		);

		$this->assertArrayNotHasKey( 'change_id', $outcome->data );
		$this->assertSame( [], $journal->recorded );
		$this->assertSame( 0, $tool->reads );
	}

	/** The store's switch decides too, not only the grant: a token can outlive the setting. */
	public function test_a_store_that_switched_history_off_records_nothing(): void {
		$tool    = $this->tool();
		$journal = $this->journal();

		$outcome = $this->dispatcher( $tool, $journal, history_switched_on: false )->dispatch(
			'a_write',
			[
				'id'            => 5,
				'regular_price' => '99',
			],
			AgentFactory::with_scopes( self::WITH_HISTORY )
		);

		$this->assertFalse( $outcome->is_error() );
		$this->assertArrayNotHasKey( 'change_id', $outcome->data );
		$this->assertSame( [], $journal->recorded );
	}

	/** Lowering a price below the sale price clears the sale, which nobody asked to send. */
	public function test_what_a_write_changed_without_being_asked_is_kept_too(): void {
		$journal = $this->journal();

		$this->dispatcher(
			$this->tool(
				[
					'regular_price' => '20',
					'sale_price'    => '15',
					'name'          => 'x',
				]
			),
			$journal
		)->dispatch(
			'a_write',
			[
				'id'            => 5,
				'regular_price' => '10',
			],
			AgentFactory::with_scopes( self::WITH_HISTORY )
		);

		$this->assertSame(
			[
				'regular_price' => '20',
				'sale_price'    => '15',
			],
			$journal->recorded[0]['before']
		);
		$this->assertSame( [ 'regular_price', 'sale_price' ], $journal->recorded[0]['plan']->fields );
	}

	/** A model asking for a dry run must never get the real write. */
	public function test_preview_on_a_tool_with_no_preview_is_refused_and_nothing_runs(): void {
		$tool = new StubTool(
			'a_plain_write',
			ApiScope::ProductsWrite,
			ToolGroup::Products,
			[
				'type'                 => 'object',
				'properties'           => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => true,
			],
			confirms: true
		);

		Functions\when( 'get_option' )->justReturn(
			[
				'products_read'  => true,
				'products_write' => true,
			]
		);
		Functions\when( 'did_action' )->justReturn( 1 );
		$registry = new ToolRegistry( new PluginSettings() );
		$registry->add( $tool );

		$outcome = ( new ToolDispatcher( $registry ) )->dispatch(
			'a_plain_write',
			[
				'id'      => 5,
				'preview' => true,
			],
			AgentFactory::with_scopes( [ 'products:write' ] )
		);

		$this->assertTrue( $outcome->is_error() );
		$this->assertStringContainsString( 'nothing was saved', $outcome->message );
		$this->assertSame( 0, $tool->calls );
	}

	/** A route can accept a field it does not return, and then nothing can be put back. */
	public function test_a_field_the_route_does_not_return_means_no_change_id_but_the_write_stands(): void {
		$tool    = $this->tool( state: [ 'name' => 'x' ] );
		$journal = $this->journal();

		$outcome = $this->dispatcher( $tool, $journal )->dispatch(
			'a_write',
			[
				'id'            => 5,
				'regular_price' => '99',
			],
			AgentFactory::with_scopes( self::WITH_HISTORY )
		);

		$this->assertFalse( $outcome->is_error() );
		$this->assertCount( 1, $tool->executed );
		$this->assertArrayNotHasKey( 'change_id', $outcome->data );
		$this->assertArrayHasKey( 'undo_unavailable', $outcome->data );
	}

	public function test_a_journal_that_cannot_save_does_not_turn_a_write_into_a_failure(): void {
		$journal      = $this->journal();
		$journal->key = null;

		$outcome = $this->dispatcher( $this->tool(), $journal )->dispatch(
			'a_write',
			[
				'id'            => 5,
				'regular_price' => '99',
			],
			AgentFactory::with_scopes( self::WITH_HISTORY )
		);

		$this->assertFalse( $outcome->is_error() );
		$this->assertArrayNotHasKey( 'change_id', $outcome->data );
	}
}
