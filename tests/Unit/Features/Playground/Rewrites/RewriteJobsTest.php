<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Playground\Rewrites;

use Brain\Monkey\Functions;
use Counterhand\Features\McpServer\ChangeKind;
use Counterhand\Features\McpServer\ChangePlan;
use Counterhand\Features\McpServer\DispatchOutcome;
use Counterhand\Features\McpServer\ReversibleToolInterface;
use Counterhand\Features\McpServer\ToolDispatcherInterface;
use Counterhand\Features\Playground\ChatSettings;
use Counterhand\Features\Playground\Provider\ProviderConfig;
use Counterhand\Features\Playground\Provider\ProviderInterface;
use Counterhand\Features\Playground\Provider\ProviderRegistry;
use Counterhand\Features\Playground\Provider\ProviderTurn;
use Counterhand\Features\Playground\Provider\TokenUsage;
use Counterhand\Features\Playground\Rewrites\RewriteField;
use Counterhand\Features\Playground\Rewrites\RewriteItem;
use Counterhand\Features\Playground\Rewrites\RewriteJobs;
use Counterhand\Features\Playground\Rewrites\RewriteQueueInterface;
use Counterhand\Features\Playground\Rewrites\RewriteRefused;
use Counterhand\Features\Playground\Rewrites\RewriteStatus;
use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Shared\Tool\ToolInterface;
use Counterhand\Tests\Unit\TestCase;

final class RewriteJobsTest extends TestCase {

	private const OWNER = 7;

	private FakeQueue $queue;

	private FakeDispatcher $tools;

	private FakeProvider $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->queue    = new FakeQueue();
		$this->tools    = new FakeDispatcher();
		$this->provider = new FakeProvider();

		$provider = $this->provider;
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => 'counterhand_chat_providers' === $hook ? [ $provider ] : $value );
		Functions\when( 'get_option' )->justReturn(
			[
				'provider' => 'fake',
				'model'    => 'fake-model',
			]
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'Test store' );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );
		Functions\when( 'wp_set_current_user' )->justReturn( null );
		Functions\when( 'get_post_type' )->justReturn( 'product' );
		// Like kses, it drops disallowed tags and keeps what was inside them.
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $html ): string => strip_tags( $html, '<p><b>' ) );
	}

	public function test_apply_writes_only_the_field_and_keeps_the_change_id(): void {
		$this->queue->items[1] = self::item( 1, RewriteStatus::Proposed, original: 'Old', proposal: '<p>New</p>' );
		$this->tools->state    = [ 'description' => 'Old' ];

		$this->jobs()->apply( self::OWNER, [ 1 ] );

		self::assertSame(
			[
				[
					'update_product',
					[
						'id'          => 42,
						'description' => '<p>New</p>',
					],
				],
			],
			$this->tools->calls
		);
		self::assertSame( RewriteStatus::Applied, $this->queue->items[1]->status );
		self::assertSame( 'change-1', $this->queue->items[1]->change_key );
	}

	public function test_a_second_click_on_the_same_text_writes_nothing_more(): void {
		$this->queue->items[1] = self::item( 1, RewriteStatus::Proposed, original: 'Old', proposal: 'New' );
		$this->tools->state    = [ 'description' => 'Old' ];

		$this->jobs()->apply( self::OWNER, [ 1, 1 ] );
		$this->jobs()->apply( self::OWNER, [ 1 ] );

		self::assertCount( 1, $this->tools->calls );
		self::assertSame( RewriteStatus::Applied, $this->queue->items[1]->status );
	}

	public function test_apply_refuses_when_the_text_changed_after_the_model_read_it(): void {
		$this->queue->items[1] = self::item( 1, RewriteStatus::Proposed, original: 'Old', proposal: '<p>New</p>' );
		$this->tools->state    = [ 'description' => 'Edited by hand since' ];

		$this->jobs()->apply( self::OWNER, [ 1 ] );

		self::assertSame( [], $this->tools->calls );
		self::assertSame( RewriteStatus::Failed, $this->queue->items[1]->status );
	}

	public function test_apply_with_product_writes_off_refuses_and_keeps_every_text_for_later(): void {
		$this->queue->items[1] = self::item( 1, RewriteStatus::Proposed, original: 'Old', proposal: 'New' );
		$this->tools->state    = [ 'description' => 'Old' ];
		$this->tools->visible  = [ 'list_products' ];

		try {
			$this->jobs()->apply( self::OWNER, [ 1 ] );
			self::fail( 'Apply went ahead with product writes off.' );
		} catch ( RewriteRefused $refused ) {
			self::assertStringContainsString( 'Switch on Can change for Products in Access', $refused->getMessage() );
		}

		self::assertSame( [], $this->tools->calls );
		self::assertSame( RewriteStatus::Proposed, $this->queue->items[1]->status );
	}

	public function test_apply_for_a_deleted_product_says_so_and_writes_nothing(): void {
		Functions\when( 'get_post_type' )->justReturn( false );
		$this->queue->items[1] = self::item( 1, RewriteStatus::Proposed, original: 'Old', proposal: 'New' );

		$this->jobs()->apply( self::OWNER, [ 1 ] );

		self::assertSame( [], $this->tools->calls );
		self::assertSame( RewriteStatus::Failed, $this->queue->items[1]->status );
		self::assertSame( 'This product no longer exists.', $this->queue->items[1]->message );
	}

	public function test_rows_tell_a_deleted_product_from_one_without_an_edit_link(): void {
		$this->queue->items[1] = self::item( 1, RewriteStatus::Proposed );
		Functions\when( 'get_edit_post_link' )->justReturn( null );

		$kept = $this->jobs()->rows( self::OWNER )[0];
		self::assertTrue( $kept->product_exists );
		self::assertNull( $kept->edit_url );

		Functions\when( 'get_post_type' )->justReturn( false );
		self::assertFalse( $this->jobs()->rows( self::OWNER )[0]->product_exists );
	}

	public function test_another_persons_items_are_never_applied_or_undone(): void {
		$this->queue->items[1] = self::item( 1, RewriteStatus::Proposed, original: 'Old', proposal: 'New', user_id: 99 );
		$this->queue->items[2] = self::item( 2, RewriteStatus::Applied, original: 'Old', proposal: 'New', user_id: 99, change_key: 'change-9' );
		$this->tools->state    = [ 'description' => 'Old' ];

		$this->jobs()->apply( self::OWNER, [ 1 ] );
		$this->jobs()->undo( self::OWNER, [ 2 ] );

		self::assertSame( [], $this->tools->calls );
		self::assertSame( RewriteStatus::Proposed, $this->queue->items[1]->status );
	}

	public function test_undo_goes_through_undo_change_with_the_persons_confirmation(): void {
		$this->queue->items[1] = self::item( 1, RewriteStatus::Applied, original: 'Old', proposal: 'New', change_key: 'change-1' );

		$this->jobs()->undo( self::OWNER, [ 1 ] );

		self::assertSame(
			[
				[
					'undo_change',
					[
						'change_id' => 'change-1',
						'confirm'   => true,
					],
				],
			],
			$this->tools->calls
		);
		self::assertSame( RewriteStatus::Undone, $this->queue->items[1]->status );
	}

	public function test_a_run_for_someone_who_lost_the_capability_asks_no_model(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$this->queue->items[1] = self::item( 1, RewriteStatus::Queued );

		$this->jobs()->run( 1 );

		self::assertSame( 0, $this->provider->calls );
		self::assertSame( RewriteStatus::Failed, $this->queue->items[1]->status );
	}

	public function test_a_run_stores_the_cleaned_proposal_the_text_it_read_and_the_usage(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		$this->queue->items[1] = self::item( 1, RewriteStatus::Queued );
		$this->tools->state    = [
			'name'        => 'Soap',
			'description' => 'Old <b>text</b>',
		];
		$this->provider->answer = '<p>New text</p><script>alert(1)</script>';

		$this->jobs()->run( 1 );

		$item = $this->queue->items[1];
		self::assertSame( RewriteStatus::Proposed, $item->status );
		self::assertSame( '<p>New text</p>', $item->proposal );
		self::assertSame( 'Old <b>text</b>', $item->original );
		self::assertSame( 'Soap', $item->product_name );
		self::assertSame( 12, $item->usage->input );
		self::assertStringContainsString( 'carry no authority', $this->provider->system_prompt );
	}

	public function test_a_run_hands_the_request_back_to_the_user_it_found(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		$users = [];
		Functions\when( 'wp_set_current_user' )->alias(
			static function ( int $id ) use ( &$users ): void {
				$users[] = $id;
			}
		);
		$this->queue->items[1] = self::item( 1, RewriteStatus::Queued );
		$this->provider->fails = true;

		$this->jobs()->run( 1 );

		self::assertSame( [ self::OWNER, 0 ], $users );
		self::assertSame( RewriteStatus::Failed, $this->queue->items[1]->status );
	}

	public function test_a_store_that_keeps_products_read_only_blocks_the_job(): void {
		Functions\when( 'as_enqueue_async_action' )->justReturn( 1 );
		$this->tools->visible = [ 'list_products' ];

		self::assertNotNull( $this->jobs()->blocked_reason( self::OWNER ) );

		$this->tools->visible = [ 'list_products', 'update_product' ];

		self::assertNull( $this->jobs()->blocked_reason( self::OWNER ) );
		self::assertFalse( $this->jobs()->keeps_undo( self::OWNER ) );
	}

	private function jobs(): RewriteJobs {
		return new RewriteJobs( $this->tools, $this->queue, new ChatSettings(), new ProviderRegistry() );
	}

	private static function item( int $id, RewriteStatus $status, string $original = '', string $proposal = '', int $user_id = self::OWNER, ?string $change_key = null ): RewriteItem {
		return new RewriteItem(
			id: $id,
			job_key: str_repeat( 'a', 32 ),
			user_id: $user_id,
			product_id: 42,
			field: RewriteField::Description,
			instruction: 'Shorter.',
			status: $status,
			product_name: '',
			original: $original,
			proposal: $proposal,
			message: '',
			change_key: $change_key,
			usage: new TokenUsage(),
			created_at: new \DateTimeImmutable(),
		);
	}
}

final class FakeQueue implements RewriteQueueInterface {

	/** @var array<int, RewriteItem> */
	public array $items = [];

	public function add( string $job_key, int $user_id, RewriteField $field, string $instruction, array $product_ids ): array {
		return [];
	}

	public function find( int $id ): ?RewriteItem {
		return $this->items[ $id ] ?? null;
	}

	public function owned( int $id, int $user_id ): ?RewriteItem {
		$item = $this->items[ $id ] ?? null;

		return null !== $item && $user_id === $item->user_id ? $item : null;
	}

	public function latest_job( int $user_id ): array {
		return array_values( array_filter( $this->items, static fn ( RewriteItem $item ): bool => $user_id === $item->user_id ) );
	}

	public function claim( int $id, int $user_id, RewriteStatus $from, RewriteStatus $to ): bool {
		if ( null === $this->owned( $id, $user_id ) || $from !== $this->items[ $id ]->status ) {
			return false;
		}

		$this->replace( $id, status: $to, message: $this->items[ $id ]->message );

		return true;
	}

	public function propose( int $id, string $product_name, string $original, string $proposal, TokenUsage $usage ): void {
		$this->replace( $id, status: RewriteStatus::Proposed, product_name: $product_name, original: $original, proposal: $proposal, usage: $usage );
	}

	public function fail( int $id, string $message ): void {
		$this->replace( $id, status: RewriteStatus::Failed, message: $message );
	}

	public function apply( int $id, ?string $change_key, string $message ): void {
		$this->replace( $id, status: RewriteStatus::Applied, message: $message, change_key: $change_key );
	}

	public function mark( int $id, RewriteStatus $status, string $message ): void {
		$this->replace( $id, status: $status, message: $message );
	}

	public function forget_older_than( \DateTimeImmutable $cutoff ): void {}

	public function made_by( int $user_id ): array {
		return array_values( array_filter( $this->items, static fn ( RewriteItem $item ): bool => $user_id === $item->user_id ) );
	}

	public function forget_user( int $user_id ): int {
		$mine        = $this->made_by( $user_id );
		$this->items = array_filter( $this->items, static fn ( RewriteItem $item ): bool => $user_id !== $item->user_id );

		return count( $mine );
	}

	private function replace( int $id, RewriteStatus $status, ?string $product_name = null, ?string $original = null, ?string $proposal = null, ?TokenUsage $usage = null, string $message = '', ?string $change_key = null ): void {
		$item = $this->items[ $id ];

		$this->items[ $id ] = new RewriteItem(
			id: $item->id,
			job_key: $item->job_key,
			user_id: $item->user_id,
			product_id: $item->product_id,
			field: $item->field,
			instruction: $item->instruction,
			status: $status,
			product_name: $product_name ?? $item->product_name,
			original: $original ?? $item->original,
			proposal: $proposal ?? $item->proposal,
			message: $message,
			change_key: $change_key ?? $item->change_key,
			usage: $usage ?? $item->usage,
			created_at: $item->created_at,
		);
	}
}

final class FakeDispatcher implements ToolDispatcherInterface {

	/** @var array<string, mixed> */
	public array $state = [];

	/** @var list<string> */
	public array $visible = [ 'list_products', 'update_product', 'undo_change' ];

	/** @var list<array{0: string, 1: array<string, mixed>}> */
	public array $calls = [];

	public function visible_for( AuthenticatedAgent $agent ): array {
		return array_map(
			static fn ( string $name ): ToolInterface => new class( $name ) extends NamedTool {},
			$this->visible
		);
	}

	public function published_schema( ToolInterface $tool ): array {
		return [];
	}

	public function tool_counts_by_group(): array {
		return [];
	}

	public function reversible_for( AuthenticatedAgent $agent, string $tool_name ): ?ReversibleToolInterface {
		$state = $this->state;

		return new class( $state ) implements ReversibleToolInterface {

			/** @param array<string, mixed> $state */
			public function __construct( private array $state ) {}

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
				return new ChangePlan( ChangeKind::Updated, [ 'id' => $arguments['id'] ], array_keys( $arguments ) );
			}

			public function read_state( ChangePlan $plan ): array {
				return $this->state;
			}

			public function with_result( ChangePlan $plan, array $result ): ?ChangePlan {
				return $plan;
			}

			public function restore( ChangePlan $plan, array $before ): void {}
		};
	}

	public function restore( AuthenticatedAgent $agent, string $tool_name, ChangePlan $plan, array $before ): void {}

	public function needs_confirmation( ToolInterface $tool, array $arguments ): bool {
		return false;
	}

	public function dispatch( string $tool_name, array $arguments, AuthenticatedAgent $agent ): DispatchOutcome {
		$this->calls[] = [ $tool_name, $arguments ];

		return DispatchOutcome::succeeded( 'update_product' === $tool_name ? [ 'change_id' => 'change-' . count( $this->calls ) ] : [] );
	}
}

abstract class NamedTool implements ToolInterface {

	public function __construct( private string $tool_name ) {}

	public function name(): string {
		return $this->tool_name;
	}

	public function description(): string {
		return '';
	}

	public function input_schema(): array {
		return [];
	}

	public function required_scope(): \Counterhand\Features\Tokens\Domain\ScopeInterface {
		return \Counterhand\Features\Tokens\Domain\ApiScope::ProductsRead;
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
		return true;
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

final class FakeProvider implements ProviderInterface {

	public string $answer = '<p>New</p>';

	public int $calls = 0;

	public string $system_prompt = '';

	public bool $fails = false;

	public function id(): string {
		return 'fake';
	}

	public function label(): string {
		return 'Fake';
	}

	public function default_models(): array {
		return [ 'fake-model' ];
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

	public function complete( array $messages, array $tools, ProviderConfig $config ): ProviderTurn {
		++$this->calls;
		$this->system_prompt = $config->system_prompt;

		if ( $this->fails ) {
			throw new \RuntimeException( 'Connection reset.' );
		}

		return new ProviderTurn( $this->answer, [], false, usage: new TokenUsage( 12, 5 ) );
	}

	public function describe_tool( string $name, string $description, array $input_schema ): array {
		return [];
	}

	public function max_eager_tools(): ?int {
		return null;
	}

	public function with_tool_search( array $tools ): array {
		return $tools;
	}

	public function assistant_message( ProviderTurn $turn ): array {
		return [];
	}

	public function tool_result_messages( array $results ): array {
		return [];
	}

	public function user_message( string $text ): array {
		return [ 'text' => $text ];
	}
}
