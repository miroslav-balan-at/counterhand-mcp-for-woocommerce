<?php

declare( strict_types=1 );

namespace Counterhand\Features\Undo;

use Counterhand\Features\McpServer\AgentAwareToolInterface;
use Counterhand\Features\McpServer\ChangeJournalInterface;
use Counterhand\Features\McpServer\ChangeKind;
use Counterhand\Features\McpServer\ReversibleToolInterface;
use Counterhand\Features\McpServer\ToolDispatcherInterface;
use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Tokens\Domain\ScopeGroupInterface;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * This connection's history, and undoing it.
 *
 * An undo runs the original tool, reached through what this connection can see now: a
 * grant that lost a scope, or a user who lost a role, cannot put back what they could
 * once change. It refuses if the fields no longer hold what the change wrote, because
 * restoring them would silently throw away whatever came after.
 *
 * Undo restores records, not what happened because of them. An email sent stays sent.
 */
final readonly class ChangeTool implements ToolInterface, AgentAwareToolInterface {

	private const LIST_NAME = 'get_recent_changes';

	private const UNDO_NAME = ChangeJournalInterface::UNDO_TOOL;

	private const LIST_LIMIT = 50;

	private function __construct(
		private string $tool_name,
		private ChangeRepositoryInterface $changes,
		private ToolDispatcherInterface $tools,
	) {}

	/** @return list<self> */
	public static function both( ChangeRepositoryInterface $changes, ToolDispatcherInterface $tools ): array {
		return [
			new self( self::LIST_NAME, $changes, $tools ),
			new self( self::UNDO_NAME, $changes, $tools ),
		];
	}

	public function name(): string {
		return $this->tool_name;
	}

	public function description(): string {
		return $this->is_read_only()
			? sprintf( 'List the changes this connection made to products, variations, categories, coupons, shipping, taxes, posts and pages in the last %d days, newest first, with their change_id and the values before. Orders, refunds, customers, reviews, payment gateways, settings and other plugins\' tools are not recorded, and neither are custom fields, or deletions, which cannot be put back. A write with no undo says so in its result.', WpdbChangeJournal::RETENTION_DAYS )
			: 'Undo one change this connection made, by change_id: the write\'s own result gives it, and so does get_recent_changes. An update puts back the fields it changed, including any it changed without being asked, and leaves the rest alone; it is refused if those fields have changed again since. A create deletes what it created, and is refused if that has since been edited, sold or used. A field that does not go back is reported as not undone. This restores the record, not what followed from it: emails already sent stay sent, orders already placed keep their prices, and a price customers saw may still count as the lowest in the last 30 days.';
	}

	public function input_schema(): array {
		if ( $this->is_read_only() ) {
			return [
				'type'                 => 'object',
				'properties'           => [],
				'additionalProperties' => false,
			];
		}

		return [
			'type'                 => 'object',
			'properties'           => [
				'change_id' => [ 'type' => 'string' ],
			],
			'required'             => [ 'change_id' ],
			'additionalProperties' => false,
		];
	}

	public function required_scope(): ScopeInterface {
		return $this->is_read_only() ? ToolGroup::Changes->read_scope() : ( ToolGroup::Changes->write_scope() ?? ToolGroup::Changes->read_scope() );
	}

	public function group(): ScopeGroupInterface {
		return ToolGroup::Changes;
	}

	public function is_available(): bool {
		return true;
	}

	public function requires_confirmation(): bool {
		return ! $this->is_read_only();
	}

	public function is_read_only(): bool {
		return self::LIST_NAME === $this->tool_name;
	}

	public function is_destructive(): bool {
		return ! $this->is_read_only();
	}

	public function is_idempotent(): bool {
		return $this->is_read_only();
	}

	public function is_open_world(): bool {
		return false;
	}

	/** The dispatcher calls execute_as(): no answer here can be right without knowing who asks. */
	public function execute( array $arguments ): array {
		throw new ToolCallException( 'This tool needs a connection to answer for.' );
	}

	public function execute_as( AuthenticatedAgent $agent, array $arguments ): array {
		return $this->is_read_only() ? $this->list_changes( $agent ) : $this->undo( $agent, (string) ( $arguments['change_id'] ?? '' ) );
	}

	/** @return array<string, mixed> */
	private function list_changes( AuthenticatedAgent $agent ): array {
		$changes = $this->changes->recent( $agent, self::LIST_LIMIT );

		return [
			'changes' => array_map( static fn ( RecordedChange $change ): array => $change->to_array(), $changes ),
			'count'   => count( $changes ),
		];
	}

	/**
	 * @return array<string, mixed>
	 * @throws ToolCallException When it cannot be undone safely.
	 */
	private function undo( AuthenticatedAgent $agent, string $key ): array {
		$change = $this->changes->find( $key, $agent );

		if ( null === $change ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( sprintf( 'No change "%s" was found for this connection. Changes can be undone for %d days.', $key, WpdbChangeJournal::RETENTION_DAYS ) );
		}

		if ( null !== $change->undone_at ) {
			throw new ToolCallException( 'This change has already been undone.' );
		}

		$tool = $this->tools->reversible_for( $agent, $change->tool_name );

		if ( null === $tool ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( sprintf( 'This connection can no longer use %s, which made this change.', $change->tool_name ) );
		}

		if ( self::canonical( $tool->read_state( $change->plan ) ) !== self::canonical( $change->after ) ) {
			throw new ToolCallException(
				ChangeKind::Updated === $change->plan->kind
					? 'Those fields have changed again since. Undoing this would throw that away, so read them and change them back by hand if that is what the user wants.'
					: 'What this created has changed since: edited, sold or used. Deleting it would throw that away.'
			);
		}

		if ( ! $this->changes->claim( $key ) ) {
			throw new ToolCallException( 'This change has already been undone.' );
		}

		try {
			$this->tools->restore( $agent, $change->tool_name, $change->plan, $change->before );
		} catch ( \Throwable $throwable ) {
			$this->changes->release( $key );

			throw $throwable;
		}

		if ( ChangeKind::Created === $change->plan->kind ) {
			return $this->undone( $change, 'deleted' );
		}

		$not_restored = $this->not_restored( $tool, $change );

		if ( [] !== $not_restored ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( sprintf( 'Only partly undone. These did not go back and need changing by hand: %s. Tell the user, and read the record before doing anything else with it.', implode( ', ', $not_restored ) ) );
		}

		return $this->undone( $change, 'restored' );
	}

	/** @return array<string, mixed> */
	private function undone( RecordedChange $change, string $action ): array {
		return [
			'change_id' => $change->key,
			'undone'    => true,
			'action'    => $action,
			'record'    => $change->plan->path,
			'fields'    => $change->plan->fields,
		];
	}

	/**
	 * What the store really holds now. A route can answer success and leave a value alone,
	 * and an undo reported as done would then be believed.
	 *
	 * @return list<string>
	 */
	private function not_restored( ReversibleToolInterface $tool, RecordedChange $change ): array {
		$now = $tool->read_state( $change->plan );

		return array_values(
			array_filter(
				$change->plan->fields,
				static fn ( string $field ): bool => self::canonical( $now[ $field ] ?? null ) !== self::canonical( $change->before[ $field ] ?? null )
			)
		);
	}

	/** The same value read at two times can differ in nothing but its keys' order. */
	private static function canonical( mixed $value ): string {
		if ( is_array( $value ) ) {
			ksort( $value );

			return '{' . implode( ',', array_map( static fn ( string|int $key, mixed $item ): string => $key . ':' . self::canonical( $item ), array_keys( $value ), $value ) ) . '}';
		}

		return (string) wp_json_encode( $value );
	}
}
