<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Playground;

use Counterhand\Features\McpServer\ToolDispatcher;
use Counterhand\Features\McpServer\ToolRegistry;
use Counterhand\Features\Playground\AgentLoop;
use Counterhand\Features\Playground\ApprovalDecisions;
use Counterhand\Features\Playground\PendingConfirmation;
use Counterhand\Features\Playground\PendingConfirmationStore;
use Counterhand\Features\Playground\Provider\ProviderConfig;
use Counterhand\Features\Playground\Provider\ProviderTurn;
use Counterhand\Features\Playground\Provider\ToolCall;
use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Doubles\AgentFactory;
use Counterhand\Tests\Doubles\ScriptedProvider;
use Counterhand\Tests\Doubles\StubTool;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The person's gate in the chat: a turn that wants a guarded change stops
 * before anything runs, and only their decision — never the model's word —
 * lets it through.
 */
final class AgentLoopTest extends TestCase {

	private StubTool $guarded;
	private StubTool $plain;

	protected function setUp(): void {
		parent::setUp();

		$transients = [];
		Functions\when( 'set_transient' )->alias(
			static function ( string $key, mixed $value ) use ( &$transients ): bool {
				$transients[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			static function ( string $key ) use ( &$transients ): mixed {
				return $transients[ $key ] ?? false;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			static function ( string $key ) use ( &$transients ): bool {
				unset( $transients[ $key ] );
				return true;
			}
		);
		Functions\when( 'get_option' )->justReturn(
			[
				'products_read'  => true,
				'products_write' => true,
			]
		);
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof \WP_Error );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'rest_sanitize_value_from_schema' )->returnArg( 1 );
		Functions\when( 'rest_validate_value_from_schema' )->returnArg( 1 );
		Functions\when( 'do_action' )->justReturn( null );

		$this->guarded = new StubTool( 'delete_product', ApiScope::ProductsWrite, ToolGroup::Products, confirms: true );
		$this->plain   = new StubTool( 'list_products', ApiScope::ProductsRead, ToolGroup::Products );
	}

	public function test_a_turn_that_needs_approval_is_parked_before_anything_runs(): void {
		$provider = new ScriptedProvider(
			[
				new ProviderTurn(
					'Deleting it now.',
					[
						new ToolCall(
							'c1',
							'delete_product',
							[
								'id'      => 5,
								'confirm' => true,
							]
						),
						new ToolCall( 'c2', 'list_products', [] ),
					],
					true
				),
			]
		);

		$result = $this->loop()->run( $provider, $this->config(), [], 'delete product 5', $this->agent() );

		self::assertNotNull( $result->pending );
		self::assertSame( [ 'c1' ], $result->pending->gated_ids );
		self::assertSame( 0, $this->guarded->calls, 'nothing runs until the person decides' );
		self::assertSame( 0, $this->plain->calls, 'not even the harmless part of the same turn' );
		self::assertArrayNotHasKey( 'confirm', $result->pending->calls[0]->input, 'the model\'s own confirm is discarded' );
	}

	public function test_approval_runs_the_call_with_the_persons_confirmation_and_the_rest_follows(): void {
		$provider = new ScriptedProvider( [ new ProviderTurn( 'Done.', [], false ) ] );

		$result = $this->loop()->resume( $provider, $this->config(), [], $this->pending(), ApprovalDecisions::from_request( [ 'c1' => true ] ), $this->agent() );

		self::assertSame( 1, $this->guarded->calls );
		self::assertTrue( $this->guarded->received['confirm'] );
		self::assertSame( 1, $this->plain->calls );
		self::assertNull( $result->pending );
	}

	public function test_a_declined_call_never_runs_and_the_model_is_told_so(): void {
		$provider = new ScriptedProvider( [ new ProviderTurn( 'Understood.', [], false ) ] );

		$result = $this->loop()->resume( $provider, $this->config(), [], $this->pending(), ApprovalDecisions::from_request( [ 'c1' => false ] ), $this->agent() );

		self::assertSame( 0, $this->guarded->calls );
		self::assertTrue( $result->transcript[0]['declined'] );

		$refusal = array_values( array_filter( $provider->seen[0], static fn ( array $message ): bool => 'c1' === ( $message['id'] ?? null ) ) );
		self::assertTrue( $refusal[0]['is_error'] );
	}

	public function test_an_undecided_call_counts_as_declined(): void {
		$provider = new ScriptedProvider( [ new ProviderTurn( 'Understood.', [], false ) ] );

		$this->loop()->resume( $provider, $this->config(), [], $this->pending(), ApprovalDecisions::none(), $this->agent() );

		self::assertSame( 0, $this->guarded->calls );
	}

	private function pending(): PendingConfirmation {
		return new PendingConfirmation(
			str_repeat( 'a', 32 ),
			[
				new ToolCall( 'c1', 'delete_product', [ 'id' => 5 ] ),
				new ToolCall( 'c2', 'list_products', [] ),
			],
			[ 'c1' ]
		);
	}

	private function loop(): AgentLoop {
		$registry = new ToolRegistry( new PluginSettings() );
		$registry->add( $this->guarded );
		$registry->add( $this->plain );

		return new AgentLoop( new ToolDispatcher( $registry ), new PendingConfirmationStore() );
	}

	private function agent(): \Counterhand\Features\Tokens\Authentication\AuthenticatedAgent {
		return AgentFactory::with_scopes( [ 'products:read', 'products:write' ] );
	}

	private function config(): ProviderConfig {
		return new ProviderConfig( api_key: '', model: 'scripted', base_url: '', system_prompt: '' );
	}
}
