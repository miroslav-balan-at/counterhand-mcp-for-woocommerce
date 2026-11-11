<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Rewrites;

use Counterhand\Features\McpServer\ToolDispatcherInterface;
use Counterhand\Features\Playground\ChatSettings;
use Counterhand\Features\Playground\Provider\ProviderConfig;
use Counterhand\Features\Playground\Provider\ProviderRegistry;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The model only proposes. Nothing reaches a product until its owner approves that
 * one item, and the write then runs through the tool pipeline like any other call,
 * so the store's switches, the audit hook and undo history all apply.
 */
final readonly class RewriteJobs {

	public const HOOK       = 'counterhand_rewrite_item';
	public const GROUP      = 'counterhand-rewrites';
	public const PURGE_HOOK = 'counterhand_purge_rewrites';

	/** Each product is one paid model call, and a person reviews every one. */
	public const MAX_PRODUCTS = 25;

	private const READ_TOOL   = 'list_products';
	private const WRITE_TOOL  = 'update_product';
	private const UNDO_TOOL   = 'undo_change';
	private const MAX_TOKENS  = 4096;
	private const KEPT_DAYS   = 30;
	private const RUN_SECONDS = 180;

	public function __construct(
		private ToolDispatcherInterface $tools,
		private RewriteQueueInterface $queue,
		private ChatSettings $settings,
		private ProviderRegistry $providers,
	) {}

	/** Why a job cannot start for this person, or null when it can. */
	public function blocked_reason( int $user_id ): ?string {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return __( 'WooCommerce’s Action Scheduler is not available, so rewrites cannot run in the background.', 'counterhand-mcp-for-woocommerce' );
		}

		if ( null === $this->providers->get( $this->settings->provider_id() ) || ! $this->settings->is_configured() ) {
			return __( 'No model is connected yet. Connect one on the Chat screen first.', 'counterhand-mcp-for-woocommerce' );
		}

		if ( ! $this->writes_products( $user_id ) ) {
			return __( 'Products cannot be changed by AI on this store. Tick Write for Products in Settings first.', 'counterhand-mcp-for-woocommerce' );
		}

		return null;
	}

	public function writes_products( int $user_id ): bool {
		return $this->reaches( $user_id, self::WRITE_TOOL );
	}

	public function keeps_undo( int $user_id ): bool {
		return $this->reaches( $user_id, self::UNDO_TOOL );
	}

	/**
	 * @return int How many products were queued.
	 * @throws RewriteRefused When nothing can be queued.
	 */
	public function start( int $user_id, int $category_id, RewriteField $field, string $instruction ): int {
		$blocked = $this->blocked_reason( $user_id );
		if ( null !== $blocked ) {
			throw new RewriteRefused( $blocked ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- shown with esc_html by the screen.
		}

		if ( '' === trim( $instruction ) ) {
			throw new RewriteRefused( __( 'Say what the new text should do differently.', 'counterhand-mcp-for-woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- shown with esc_html by the screen.
		}

		$listed = $this->tools->dispatch(
			self::READ_TOOL,
			[
				'category' => (string) $category_id,
				'per_page' => self::MAX_PRODUCTS,
				'orderby'  => 'id',
				'order'    => 'asc',
			],
			RewriteAgent::for_user( $user_id )
		);

		if ( $listed->is_error() ) {
			throw new RewriteRefused( $listed->message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- shown with esc_html by the screen.
		}

		$product_ids = array_values(
			array_filter(
				array_map( static fn ( mixed $product ): int => is_array( $product ) ? (int) ( $product['id'] ?? 0 ) : 0, (array) ( $listed->data['products'] ?? [] ) ),
				static fn ( int $id ): bool => $id > 0
			)
		);

		if ( [] === $product_ids ) {
			throw new RewriteRefused( __( 'That category has no products.', 'counterhand-mcp-for-woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- shown with esc_html by the screen.
		}

		$item_ids = $this->queue->add( bin2hex( random_bytes( 16 ) ), $user_id, $field, $instruction, $product_ids );

		foreach ( $item_ids as $item_id ) {
			as_enqueue_async_action( self::HOOK, [ 'item' => $item_id ], self::GROUP );
		}

		return count( $item_ids );
	}

	/**
	 * Runs in the background with no user, so it takes on the job's owner and asks
	 * again: someone demoted since starting the job gets nothing written for them.
	 */
	public function run( int $item_id ): void {
		$item = $this->queue->find( $item_id );
		if ( null === $item || RewriteStatus::Queued !== $item->status ) {
			return;
		}

		// The scheduler runs other plugins' actions in the same request, which must not inherit this user.
		$previous = get_current_user_id();
		wp_set_current_user( $item->user_id );

		try {
			$this->write_proposal( $item );
		} finally {
			wp_set_current_user( $previous );
		}
	}

	private function write_proposal( RewriteItem $item ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			$this->queue->fail( $item->id, __( 'The person who started this job can no longer manage the store.', 'counterhand-mcp-for-woocommerce' ) );

			return;
		}

		$provider = $this->providers->get( $this->settings->provider_id() );
		if ( null === $provider ) {
			$this->queue->fail( $item->id, __( 'No model is connected any more.', 'counterhand-mcp-for-woocommerce' ) );

			return;
		}

		try {
			$state   = $this->stored( $item->user_id, $item->product_id, $item->field );
			$name    = is_string( $state['name'] ?? null ) ? $state['name'] : '';
			$current = is_string( $state[ $item->field->value ] ?? null ) ? $state[ $item->field->value ] : '';

			if ( function_exists( 'wc_set_time_limit' ) ) {
				wc_set_time_limit( self::RUN_SECONDS );
			}

			$turn = $provider->complete(
				[ $provider->user_message( RewritePrompt::request( $item->instruction, $name, $current ) ) ],
				[],
				new ProviderConfig(
					api_key: $this->settings->api_key(),
					model: $this->settings->model(),
					base_url: $this->settings->base_url(),
					system_prompt: RewritePrompt::system( $item->field ),
					max_tokens: self::MAX_TOKENS,
				)
			);
		} catch ( ToolCallException | RewriteRefused $refused ) {
			// Providers and tools word their refusals for the person, as the chat shows them.
			$this->queue->fail( $item->id, $refused->getMessage() );

			return;
		} catch ( \Throwable $throwable ) {
			$this->fail_unexpectedly( $item, $throwable );

			return;
		}

		// kses drops a script tag but keeps its code, which would then show as text on the product page.
		$proposal = trim( wp_kses_post( (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $turn->text ) ) );
		if ( '' === $proposal ) {
			$this->queue->fail( $item->id, __( 'The model returned no text.', 'counterhand-mcp-for-woocommerce' ) );

			return;
		}

		$this->queue->propose( $item->id, $name, $current, $proposal, $turn->usage );
	}

	/**
	 * @param list<int> $item_ids
	 * @throws RewriteRefused When products cannot be written, before any text leaves Ready to review.
	 */
	public function apply( int $user_id, array $item_ids ): void {
		// Checked before claiming: a claimed text that cannot be written would fail for good.
		if ( ! $this->writes_products( $user_id ) ) {
			throw new RewriteRefused( __( 'Products cannot be changed by AI on this store. Tick Write for Products in Settings, then apply again.', 'counterhand-mcp-for-woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- shown with esc_html by the screen.
		}

		$agent = RewriteAgent::for_user( $user_id );

		foreach ( $this->claimed( $user_id, $item_ids, RewriteStatus::Proposed, RewriteStatus::Applying ) as $item ) {
			if ( ! self::product_exists( $item->product_id ) ) {
				$this->queue->fail( $item->id, __( 'This product no longer exists.', 'counterhand-mcp-for-woocommerce' ) );

				continue;
			}

			try {
				$current = $this->stored( $user_id, $item->product_id, $item->field )[ $item->field->value ] ?? null;
			} catch ( ToolCallException | RewriteRefused $refused ) {
				$this->queue->fail( $item->id, $refused->getMessage() );

				continue;
			} catch ( \Throwable $throwable ) {
				$this->fail_unexpectedly( $item, $throwable );

				continue;
			}

			// Someone edited the product after the model read it; writing now would throw that edit away.
			if ( $current !== $item->original ) {
				$this->queue->mark( $item->id, RewriteStatus::Failed, __( 'The product’s text changed after the model read it. Start a new job to rewrite the current text.', 'counterhand-mcp-for-woocommerce' ) );

				continue;
			}

			$outcome = $this->tools->dispatch(
				self::WRITE_TOOL,
				[
					'id'                => $item->product_id,
					$item->field->value => $item->proposal,
				],
				$agent
			);

			if ( $outcome->is_error() ) {
				$this->queue->fail( $item->id, $outcome->message );

				continue;
			}

			$change_key = is_string( $outcome->data['change_id'] ?? null ) ? $outcome->data['change_id'] : null;

			$this->queue->apply(
				$item->id,
				$change_key,
				null === $change_key ? __( 'Applied without undo history, so this cannot be undone here.', 'counterhand-mcp-for-woocommerce' ) : ''
			);
		}
	}

	/** @param list<int> $item_ids */
	public function undo( int $user_id, array $item_ids ): void {
		$agent = RewriteAgent::for_user( $user_id );

		foreach ( $this->claimed( $user_id, $item_ids, RewriteStatus::Applied, RewriteStatus::Undoing ) as $item ) {
			if ( null === $item->change_key ) {
				$this->queue->mark( $item->id, RewriteStatus::Applied, $item->message );

				continue;
			}

			// The person ticking this item and pressing Undo is the confirmation undo asks for.
			$outcome = $this->tools->dispatch(
				self::UNDO_TOOL,
				[
					'change_id' => $item->change_key,
					'confirm'   => true,
				],
				$agent
			);

			if ( $outcome->is_error() ) {
				$this->queue->mark( $item->id, RewriteStatus::Applied, $outcome->message );

				continue;
			}

			$this->queue->mark( $item->id, RewriteStatus::Undone, '' );
		}
	}

	/** Rows keep a person's instructions, so they go when undo history does, whether or not anyone starts a job. */
	public function purge(): void {
		$this->queue->forget_older_than( new \DateTimeImmutable( '-' . self::KEPT_DAYS . ' days' ) );
	}

	/** @return list<RewriteRow> */
	public function rows( int $user_id ): array {
		return array_map(
			static fn ( RewriteItem $item ): RewriteRow => new RewriteRow(
				$item,
				self::product_exists( $item->product_id ),
				get_edit_post_link( $item->product_id, 'raw' )
			),
			$this->queue->latest_job( $user_id )
		);
	}

	/** @param list<int> $item_ids */
	public function discard( int $user_id, array $item_ids ): void {
		// Claiming is the whole of discarding.
		$this->claimed( $user_id, $item_ids, RewriteStatus::Proposed, RewriteStatus::Discarded );
	}

	/**
	 * The stored text, as update_product would send it back, read through the tool
	 * the job writes with, so a person who cannot see the product reads nothing.
	 *
	 * @return array<string, mixed>
	 * @throws RewriteRefused When this person cannot change products.
	 * @throws ToolCallException When the product cannot be read.
	 */
	private function stored( int $user_id, int $product_id, RewriteField $field ): array {
		$tool = $this->tools->reversible_for( RewriteAgent::for_user( $user_id ), self::WRITE_TOOL );
		if ( null === $tool ) {
			throw new RewriteRefused( __( 'Products cannot be changed by AI on this store any more.', 'counterhand-mcp-for-woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- stored as a message and shown with esc_html.
		}

		return $tool->read_state(
			$tool->change_plan(
				[
					'id'          => $product_id,
					'name'        => '',
					$field->value => '',
				]
			)
		);
	}

	private function fail_unexpectedly( RewriteItem $item, \Throwable $throwable ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( 'Bulk rewrite failed: ' . $throwable->getMessage(), [ 'source' => 'counterhand-mcp' ] );
		}

		$this->queue->fail( $item->id, __( 'The rewrite failed unexpectedly. Check the WooCommerce logs for details.', 'counterhand-mcp-for-woocommerce' ) );
	}

	/**
	 * This person's items that were in one status and are now theirs to act on.
	 *
	 * @param  list<int> $item_ids
	 * @return list<RewriteItem>
	 */
	private function claimed( int $user_id, array $item_ids, RewriteStatus $from, RewriteStatus $to ): array {
		$claimed = [];

		foreach ( $item_ids as $item_id ) {
			$item = $this->queue->owned( $item_id, $user_id );

			if ( null !== $item && $this->queue->claim( $item_id, $user_id, $from, $to ) ) {
				$claimed[] = $item;
			}
		}

		return $claimed;
	}

	private static function product_exists( int $product_id ): bool {
		return 'product' === get_post_type( $product_id );
	}

	private function reaches( int $user_id, string $tool_name ): bool {
		return [] !== array_filter(
			$this->tools->visible_for( RewriteAgent::for_user( $user_id ) ),
			static fn ( ToolInterface $tool ): bool => $tool_name === $tool->name()
		);
	}
}
