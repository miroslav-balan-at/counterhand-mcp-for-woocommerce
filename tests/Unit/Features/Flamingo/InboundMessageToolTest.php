<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Flamingo;

use Counterhand\Features\Flamingo\InboundMessage;
use Counterhand\Features\Flamingo\InboundMessageOperation;
use Counterhand\Features\Flamingo\InboundMessagePage;
use Counterhand\Features\Flamingo\InboundMessageStatus;
use Counterhand\Features\Flamingo\InboundMessageTool;
use Counterhand\Features\Flamingo\InboxInterface;
use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Tests\Unit\TestCase;

/**
 * No REST permission callback stands behind these tools, so the checks asserted
 * here are the only authorization there is.
 */
final class InboundMessageToolTest extends TestCase {

	private function message( int $id ): InboundMessage {
		return new InboundMessage( $id, 'Hello', 'Jane', 'jane@example.test', 'contact', '2026-10-30 10:00:00', InboundMessageStatus::Inbox, [ 'text' => 'Hi' ], [] );
	}

	private function inbox( bool $installed = true, bool $can_list = true, bool $can_read = true, bool $can_trash = true ): InboxInterface {
		return new class( $this->message( 7 ), $installed, $can_list, $can_read, $can_trash ) implements InboxInterface {

			/** @var list<int> */
			public array $trashed = [];

			public function __construct(
				private InboundMessage $message,
				private bool $installed,
				private bool $can_list,
				private bool $can_read,
				private bool $can_trash,
			) {}

			public function is_installed(): bool {
				return $this->installed;
			}

			public function page( InboundMessageStatus $status, int $per_page, int $page, string $search ): InboundMessagePage {
				return new InboundMessagePage( [ $this->message ], 41 );
			}

			public function find( int $id ): ?InboundMessage {
				return $this->message->id === $id ? $this->message : null;
			}

			public function status_of( int $id ): ?InboundMessageStatus {
				return $this->message->id === $id ? $this->message->status : null;
			}

			public function trash( int $id ): bool {
				$this->trashed[] = $id;

				return true;
			}

			public function user_can_list(): bool {
				return $this->can_list;
			}

			public function user_can_read( int $id ): bool {
				return $this->can_read;
			}

			public function user_can_trash( int $id ): bool {
				return $this->can_trash;
			}
		};
	}

	private function tool( InboundMessageOperation $operation, InboxInterface $inbox ): InboundMessageTool {
		return new InboundMessageTool( $operation, $inbox, IntegrationGroup::of( 'flamingo' ) );
	}

	public function test_a_list_summarizes_without_the_message_body_and_reports_totals(): void {
		$result = $this->tool( InboundMessageOperation::List, $this->inbox() )->execute( [ 'per_page' => 20 ] );

		$this->assertSame( 41, $result['total'] );
		$this->assertSame( 3, $result['total_pages'] );
		$this->assertArrayNotHasKey( 'fields', $result['messages'][0] );
	}

	public function test_reading_one_includes_what_the_person_wrote(): void {
		$result = $this->tool( InboundMessageOperation::Get, $this->inbox() )->execute( [ 'id' => 7 ] );

		$this->assertSame( [ 'text' => 'Hi' ], $result['message']['fields'] );
	}

	/** An id that is another post, or one this user may not read, gets one answer. */
	public function test_an_unreachable_message_and_a_missing_one_read_the_same(): void {
		$messages = [];

		foreach ( [ [ 7, $this->inbox( can_read: false ) ], [ 8, $this->inbox() ] ] as [ $id, $inbox ] ) {
			try {
				$this->tool( InboundMessageOperation::Get, $inbox )->execute( [ 'id' => $id ] );
				$this->fail( 'Message ' . $id . ' should have been refused.' );
			} catch ( ToolCallException $exception ) {
				$messages[] = str_replace( (string) $id, 'N', $exception->getMessage() );
			}
		}

		$this->assertSame( $messages[0], $messages[1] );
	}

	public function test_trash_is_refused_without_the_capability_and_touches_nothing(): void {
		$inbox = $this->inbox( can_trash: false );

		try {
			$this->tool( InboundMessageOperation::Trash, $inbox )->execute( [ 'id' => 7 ] );
			$this->fail( 'Trashing should have been refused.' );
		} catch ( ToolCallException ) {
			$this->assertSame( [], $inbox->trashed );
		}
	}

	public function test_trash_is_a_gated_destructive_write_behind_the_write_scope(): void {
		$inbox = $this->inbox();
		$tool  = $this->tool( InboundMessageOperation::Trash, $inbox );

		$this->assertTrue( $tool->requires_confirmation() );
		$this->assertTrue( $tool->is_destructive() );
		$this->assertSame( 'integration:flamingo:write', $tool->required_scope()->key() );
		$this->assertSame( [ 'id' => 7, 'trashed' => true ], $tool->execute( [ 'id' => 7 ] ) );
		$this->assertSame( [ 7 ], $inbox->trashed );
	}

	/** A retry after a dropped response finds it already there. */
	public function test_trashing_a_message_already_in_the_trash_succeeds_without_touching_it(): void {
		$inbox = new class( $this->inbox() ) implements InboxInterface {

			/** @var list<int> */
			public array $trashed = [];

			public function __construct( private InboxInterface $inner ) {}

			public function is_installed(): bool {
				return true;
			}

			public function page( InboundMessageStatus $status, int $per_page, int $page, string $search ): InboundMessagePage {
				return $this->inner->page( $status, $per_page, $page, $search );
			}

			public function find( int $id ): ?InboundMessage {
				return $this->inner->find( $id );
			}

			public function status_of( int $id ): ?InboundMessageStatus {
				return InboundMessageStatus::Trash;
			}

			public function trash( int $id ): bool {
				$this->trashed[] = $id;

				return false;
			}

			public function user_can_list(): bool {
				return true;
			}

			public function user_can_read( int $id ): bool {
				return true;
			}

			public function user_can_trash( int $id ): bool {
				return true;
			}
		};

		$this->assertTrue( $this->tool( InboundMessageOperation::Trash, $inbox )->execute( [ 'id' => 7 ] )['trashed'] );
		$this->assertSame( [], $inbox->trashed );
	}

	public function test_only_reads_promise_to_be_idempotent(): void {
		$this->assertTrue( $this->tool( InboundMessageOperation::Get, $this->inbox() )->is_idempotent() );
		$this->assertFalse( $this->tool( InboundMessageOperation::Trash, $this->inbox() )->is_idempotent() );
	}

	public function test_reads_sit_behind_the_read_scope_and_need_no_confirmation(): void {
		$tool = $this->tool( InboundMessageOperation::List, $this->inbox() );

		$this->assertFalse( $tool->requires_confirmation() );
		$this->assertSame( 'integration:flamingo:read', $tool->required_scope()->key() );
	}

	/** @dataProvider unavailable_inboxes */
	public function test_it_is_hidden_when_flamingo_is_gone_or_the_user_cannot_list( bool $installed, bool $can_list ): void {
		$this->assertFalse( $this->tool( InboundMessageOperation::List, $this->inbox( $installed, $can_list ) )->is_available() );
	}

	/** @return iterable<string, array{bool, bool}> */
	public static function unavailable_inboxes(): iterable {
		yield 'plugin gone' => [ false, true ];
		yield 'no capability' => [ true, false ];
	}
}
