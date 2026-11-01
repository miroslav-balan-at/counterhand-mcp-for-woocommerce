<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Shared\Exception\ScopeDeniedException;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The tool pipeline itself: resolve, default, validate, gate, execute, audit.
 *
 * Extracted from the JSON-RPC handler so an in-process caller (the admin
 * playground) runs the exact same pipeline an external MCP client does,
 * without building an envelope or reading wire shapes back out.
 */
final readonly class ToolDispatcher implements ToolDispatcherInterface {

	private const CONFIRM_ARGUMENT = 'confirm';

	private const PREVIEW_ARGUMENT = 'preview';

	private const UNDO_UNAVAILABLE = 'undo_unavailable';

	private const NO_UNDO_MESSAGE = 'This change was saved, but it has no undo. Tell the user if they may need to reverse it.';

	private const NESTED_SCHEMAS = [ 'items', 'additionalProperties' ];

	private const SCHEMA_LISTS = [ 'anyOf', 'oneOf', 'allOf' ];

	public function __construct(
		private ToolRegistry $registry,
		private ?ChangeJournalInterface $journal = null,
	) {}

	/** @return list<ToolInterface> */
	public function visible_for( AuthenticatedAgent $agent ): array {
		return $this->registry->visible_for( $agent );
	}

	/** @return array<string, int> */
	public function tool_counts_by_group(): array {
		return $this->registry->tool_counts_by_group();
	}

	/**
	 * The one place an empty argument list becomes {}: an empty PHP array would
	 * encode as [], which providers and strict clients reject, while core's sanitizer
	 * throws on an object. Everything before this edge keeps properties an array.
	 */
	public function published_schema( ToolInterface $tool ): array {
		$schema = $this->gated_schema( $tool );

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			$schema = self::with_required_as_list( $schema );
		}

		if ( [] === ( $schema['properties'] ?? null ) ) {
			$schema['properties'] = new \stdClass();
		}

		return $schema;
	}

	/**
	 * WordPress accepts required: true on a property, and JSON Schema does not: strict
	 * clients and providers reject it, sometimes with the whole request. Core's own
	 * validators need it, so only the published form changes.
	 *
	 * @param  array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	private static function with_required_as_list( array $schema ): array {
		$required = is_array( $schema['required'] ?? null ) ? $schema['required'] : [];

		foreach ( self::NESTED_SCHEMAS as $keyword ) {
			if ( is_array( $schema[ $keyword ] ?? null ) ) {
				$schema[ $keyword ] = self::with_required_as_list( $schema[ $keyword ] );
			}
		}

		foreach ( self::SCHEMA_LISTS as $keyword ) {
			if ( is_array( $schema[ $keyword ] ?? null ) ) {
				$schema[ $keyword ] = array_map(
					static fn ( mixed $branch ): mixed => is_array( $branch ) ? self::with_required_as_list( $branch ) : $branch,
					$schema[ $keyword ]
				);
			}
		}

		foreach ( is_array( $schema['properties'] ?? null ) ? $schema['properties'] : [] as $name => $property ) {
			if ( ! is_array( $property ) ) {
				continue;
			}

			if ( true === ( $property['required'] ?? null ) ) {
				$required[] = (string) $name;
			}

			$schema['properties'][ $name ] = self::with_required_as_list( $property );
		}

		unset( $schema['required'] );

		if ( [] !== $required ) {
			$schema['required'] = array_values( array_unique( $required ) );
		}

		return $schema;
	}

	/**
	 * A required `confirm` in the schema rather than only in the prose, because a
	 * model reads the schema far more reliably than a warning, and the first
	 * attempt then fails loudly rather than succeeding quietly.
	 *
	 * @return array<string, mixed>
	 */
	private function gated_schema( ToolInterface $tool ): array {
		$schema = $tool->input_schema();

		if ( $tool instanceof ReversibleToolInterface && $tool->is_reversible() ) {
			$schema['properties'][ self::PREVIEW_ARGUMENT ] = [
				'type'        => 'boolean',
				'description' => 'Set true to see what this would change, field by field, without saving anything. Needs no confirmation.',
			];
		}

		if ( ! $tool->requires_confirmation() && $tool instanceof ConditionalConfirmationInterface && [] !== $tool->fields_needing_confirmation() ) {
			$schema['properties'][ self::CONFIRM_ARGUMENT ] = [
				'type'        => 'boolean',
				'description' => sprintf( 'Must be true when sending %s. Set it only after telling the user what this will change and getting their agreement.', implode( ' or ', $tool->fields_needing_confirmation() ) ),
			];

			return $schema;
		}

		if ( ! $tool->requires_confirmation() ) {
			return $schema;
		}

		$schema['properties'][ self::CONFIRM_ARGUMENT ] = [
			'type'        => 'boolean',
			'description' => 'Must be true. Set it only after telling the user what this will change and getting their agreement.',
		];

		$schema['required'] = [ ...( is_array( $schema['required'] ?? null ) ? $schema['required'] : [] ), self::CONFIRM_ARGUMENT ];

		return $schema;
	}

	public function dispatch( string $tool_name, array $arguments, AuthenticatedAgent $agent ): DispatchOutcome {
		// Resolving through the agent-filtered set makes disabled or
		// out-of-scope tools indistinguishable from nonexistent ones.
		$tool = $this->registry->resolve_for( $agent, $tool_name );
		if ( null === $tool ) {
			return DispatchOutcome::rejected( sprintf( 'Unknown tool "%s".', $tool_name ) );
		}

		if ( self::asks_unsupported_preview( $tool, $arguments ) ) {
			return $this->failure( $tool, $agent, $arguments, sprintf( '%s has no preview, and nothing was saved. Without the preview argument this call makes the change for real.', $tool->name() ) );
		}

		$validated = $arguments;

		try {
			$checked = $this->validate_arguments( $tool, $arguments );
			if ( is_wp_error( $checked ) ) {
				// The spec lists input validation among tool errors, so the model can read and correct it.
				return $this->failure( $tool, $agent, $arguments, $checked->get_error_message() );
			}
			$validated = $checked;

			// Defense in depth: the registry already filtered by scope, but the
			// gate inside the dispatch path must hold on its own.
			$agent->require_scope( $tool->required_scope() );

			$data = $tool instanceof ReversibleToolInterface && self::is_preview( $tool, $validated ) ? $this->preview( $tool, $validated ) : $this->run( $tool, $this->confirmed_arguments( $tool, $validated ), $agent );

			/**
			 * Fires after every tool call (success or failure) — the ActionLog
			 * slice subscribes here.
			 *
			 * @param string $tool_name   Tool that ran.
			 * @param string $token_label Label of the calling token.
			 * @param bool   $is_error    Whether the call failed.
			 * @param array  $arguments   Validated tool arguments.
			 * @param string $group       Slug of the tool's group, so a listener can
			 *                            decide by area without resolving the tool.
			 */
			do_action( 'counterhand_tool_called', $tool->name(), $agent->token->label, false, $validated, $tool->group()->key() );

			return DispatchOutcome::succeeded( $data );
		} catch ( ScopeDeniedException | ToolCallException $exception ) {
			// Both carry a message written for an agent to act on.
			return $this->failure( $tool, $agent, $validated, $exception->getMessage() );
		} catch ( \Throwable $throwable ) {
			// A bug rather than a refusal: a TypeError from a tool used to escape
			// as an opaque protocol error, which also skipped the hook below and
			// silently lost the call's audit row. The agent gets a message it can
			// act on, and the detail goes where an administrator can find it.
			$this->log_failure( $tool, $throwable );

			return $this->failure(
				$tool,
				$agent,
				$validated,
				'The tool failed unexpectedly. This is a fault in the store, not in the request — retrying the same call is unlikely to help.'
			);
		}
	}

	/**
	 * A model asking for a dry run must never get the real write. A closed schema would
	 * drop the argument and an open one pass it on, and either way the change would run.
	 *
	 * @param array<string, mixed> $arguments
	 */
	private static function asks_unsupported_preview( ToolInterface $tool, array $arguments ): bool {
		if ( ! array_key_exists( self::PREVIEW_ARGUMENT, $arguments ) || in_array( $arguments[ self::PREVIEW_ARGUMENT ], [ false, 0, '0', 'false', '', null ], true ) ) {
			return false;
		}

		$declares_its_own = isset( $tool->input_schema()['properties'][ self::PREVIEW_ARGUMENT ] );

		return ! $tool->is_read_only() && ! $declares_its_own && ! ( $tool instanceof ReversibleToolInterface && $tool->is_reversible() );
	}

	/** @param array<string, mixed> $arguments */
	private static function is_preview( ToolInterface $tool, array $arguments ): bool {
		return true === ( $arguments[ self::PREVIEW_ARGUMENT ] ?? false ) && $tool instanceof ReversibleToolInterface && $tool->is_reversible();
	}

	/**
	 * What a write would change, against what is stored. The tool's own rules run, so a
	 * call it would refuse is refused here too.
	 *
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function preview( ReversibleToolInterface $tool, array $arguments ): array {
		unset( $arguments[ self::PREVIEW_ARGUMENT ], $arguments[ self::CONFIRM_ARGUMENT ] );

		$plan    = $tool->change_plan( $arguments );
		$before  = ChangeKind::Updated === $plan->kind ? $tool->read_state( $plan ) : [];
		$changes = [];

		foreach ( $plan->fields as $field ) {
			$after     = $arguments[ $field ] ?? null;
			$changes[] = [
				'field'  => $field,
				'before' => $before[ $field ] ?? null,
				'after'  => is_array( $after ) && isset( $after['raw'] ) ? $after['raw'] : $after,
			];
		}

		return [
			'preview' => true,
			'saved'   => false,
			'action'  => $plan->kind->value,
			'changes' => $changes,
		];
	}

	/**
	 * Runs the tool, keeping what an undo needs when this connection keeps a history.
	 *
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function run( ToolInterface $tool, array $arguments, AuthenticatedAgent $agent ): array {
		unset( $arguments[ self::PREVIEW_ARGUMENT ] );

		if ( $tool instanceof AgentAwareToolInterface ) {
			return $tool->execute_as( $agent, $arguments );
		}

		if ( ! $tool instanceof ReversibleToolInterface || ! $tool->is_reversible() || ! $this->keeps_history_for( $agent ) ) {
			return $tool->execute( $arguments );
		}

		if ( ! $tool->is_undoable() ) {
			return [
				...$tool->execute( $arguments ),
				self::UNDO_UNAVAILABLE => self::NO_UNDO_MESSAGE,
			];
		}

		$plan     = $tool->change_plan( $arguments );
		$watching = $plan->with_fields( array_values( array_unique( [ ...$plan->fields, ...$tool->watched_fields() ] ) ) );
		$before   = $this->state_before( $tool, $plan, $watching );
		$data     = $tool->execute( $arguments );

		if ( null === $before ) {
			return [
				...$data,
				self::UNDO_UNAVAILABLE => self::NO_UNDO_MESSAGE,
			];
		}

		$change_id = $this->record( $tool, $plan, $watching, $before, $data, $agent );

		return null === $change_id
			? [
				...$data,
				self::UNDO_UNAVAILABLE => self::NO_UNDO_MESSAGE,
			]
			: [
				...$data,
				'change_id' => $change_id,
			];
	}

	private function keeps_history_for( AuthenticatedAgent $agent ): bool {
		return null !== $this->journal && null !== $this->registry->resolve_for( $agent, ChangeJournalInterface::UNDO_TOOL );
	}

	public function reversible_for( AuthenticatedAgent $agent, string $tool_name ): ?ReversibleToolInterface {
		$tool = $this->registry->resolve_for( $agent, $tool_name );

		return $tool instanceof ReversibleToolInterface ? $tool : null;
	}

	public function restore( AuthenticatedAgent $agent, string $tool_name, ChangePlan $plan, array $before ): void {
		$tool = $this->registry->resolve_for( $agent, $tool_name );

		if ( ! $tool instanceof ReversibleToolInterface ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( sprintf( 'This connection can no longer use %s, which made this change.', $tool_name ) );
		}

		$agent->require_scope( $tool->required_scope() );
		$tool->restore( $plan, $before );

		/** This action is documented in dispatch(). */
		do_action( 'counterhand_tool_called', $tool->name(), $agent->token->label, false, [ ...$plan->path, ...$before ], $tool->group()->key() );
	}

	/**
	 * Null when it cannot be put back, and the write still goes ahead: a route may accept a
	 * field it does not return, and the agent is then told there is no undo.
	 *
	 * @return array<string, mixed>|null
	 */
	private function state_before( ReversibleToolInterface $tool, ChangePlan $plan, ChangePlan $watching ): ?array {
		if ( ChangeKind::Created === $plan->kind ) {
			return [];
		}

		try {
			$before = $tool->read_state( $watching );
		} catch ( ToolCallException ) {
			return null;
		}

		return [] === array_diff( $plan->fields, array_keys( $before ) ) ? $before : null;
	}

	/**
	 * A failure here must not turn a write that succeeded into a reported failure.
	 *
	 * @param array<string, mixed> $before
	 * @param array<string, mixed> $data
	 */
	private function record( ReversibleToolInterface&ToolInterface $tool, ChangePlan $plan, ChangePlan $watching, array $before, array $data, AuthenticatedAgent $agent ): ?string {
		try {
			$done = $tool->with_result( ChangeKind::Created === $plan->kind ? $plan : $watching, $data );

			if ( null === $done || null === $this->journal ) {
				return null;
			}

			$after = $tool->read_state( $done );

			if ( ChangeKind::Created === $plan->kind ) {
				return $this->journal->record( $agent, $tool->name(), $done, [], $after );
			}

			$changed = array_values(
				array_filter(
					array_keys( $after ),
					static fn ( string $field ): bool => in_array( $field, $plan->fields, true ) || wp_json_encode( $before[ $field ] ?? null ) !== wp_json_encode( $after[ $field ] )
				)
			);
			$fields  = array_flip( $changed );

			return $this->journal->record( $agent, $tool->name(), $plan->with_fields( $changed ), array_intersect_key( $before, $fields ), array_intersect_key( $after, $fields ) );
		} catch ( \Throwable $throwable ) {
			$this->log_failure( $tool, $throwable );

			return null;
		}
	}

	/**
	 * The tool never sees `confirm`: it is this pipeline's argument, and sending it
	 * on would be an unknown parameter to whatever the tool wraps.
	 *
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 * @throws ToolCallException When a call needing confirmation does not carry confirm set to true.
	 */
	private function confirmed_arguments( ToolInterface $tool, array $arguments ): array {
		if ( $this->needs_confirmation( $tool, $arguments ) && true !== ( $arguments[ self::CONFIRM_ARGUMENT ] ?? false ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException(
				'This changes the store in a way that is not easily undone. Tell the user exactly what will happen, and call it again with confirm set to true only once they have agreed.'
			);
		}

		unset( $arguments[ self::CONFIRM_ARGUMENT ] );

		return $arguments;
	}

	public function needs_confirmation( ToolInterface $tool, array $arguments ): bool {
		if ( self::is_preview( $tool, $arguments ) ) {
			return false;
		}

		if ( $tool->requires_confirmation() ) {
			return true;
		}

		if ( ! $tool instanceof ConditionalConfirmationInterface ) {
			return false;
		}

		foreach ( $tool->fields_needing_confirmation() as $field ) {
			if ( array_key_exists( $field, $arguments ) && false !== $arguments[ $field ] ) {
				return true;
			}
		}

		return false;
	}

	public function resolve_for( AuthenticatedAgent $agent, string $tool_name ): ?ToolInterface {
		return $this->registry->resolve_for( $agent, $tool_name );
	}

	/**
	 * Core's validator raises a notice on a schema with no type, and some routes publish
	 * one. Core takes the first type a value passes, so the order keeps a value as sent:
	 * "array" first would wrap a string in a list, "boolean" before "integer" turns 1 into true, and "number" alone turns 5 into 5.0.
	 *
	 * @param  array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	private static function with_explicit_types( array $schema ): array {
		if ( ! array_key_exists( 'type', $schema ) ) {
			$schema['type'] = [ 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ];
		}

		if ( is_array( $schema['properties'] ?? null ) ) {
			$schema['properties'] = array_map( static fn ( mixed $property ): mixed => is_array( $property ) ? self::with_explicit_types( $property ) : $property, $schema['properties'] );
		}

		foreach ( self::NESTED_SCHEMAS as $keyword ) {
			if ( is_array( $schema[ $keyword ] ?? null ) ) {
				$schema[ $keyword ] = self::with_explicit_types( $schema[ $keyword ] );
			}
		}

		return $schema;
	}

	/**
	 * A failed call is still a call: it is audited like a successful one, and it
	 * comes back as an outcome the agent can read and self-correct from.
	 *
	 * @param array<string, mixed> $arguments Validated arguments, as executed.
	 */
	private function failure( ToolInterface $tool, AuthenticatedAgent $agent, array $arguments, string $message ): DispatchOutcome {
		/** This hook is documented above. */
		do_action( 'counterhand_tool_called', $tool->name(), $agent->token->label, true, $arguments, $tool->group()->key() );

		return DispatchOutcome::failed( $message );
	}

	/** The unexpected is worth recording whether or not the store logs tool calls. */
	private function log_failure( ToolInterface $tool, \Throwable $throwable ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		wc_get_logger()->error(
			sprintf(
				'Tool "%s" threw %s: %s in %s:%d',
				$tool->name(),
				$throwable::class,
				$throwable->getMessage(),
				$throwable->getFile(),
				$throwable->getLine()
			),
			[ 'source' => 'counterhand-mcp' ]
		);
	}

	private function validate_arguments( ToolInterface $tool, array $arguments ): array|\WP_Error {
		$schema = $this->gated_schema( $tool );

		// Enforced by confirmed_arguments(), whose refusal tells the model what to do. Core's
		// "is a required property" would not, and a preview needs no confirmation at all.
		if ( is_array( $schema['required'] ?? null ) ) {
			$schema['required'] = array_values( array_diff( $schema['required'], [ self::CONFIRM_ARGUMENT ] ) );
		}

		// rest_sanitize/validate_value_from_schema do NOT apply defaults —
		// without this, absent args fall through to WooCommerce's own defaults
		// (e.g. create_product would publish instead of draft).
		$arguments = $this->apply_schema_defaults( $schema, $arguments );
		$arguments = $this->parse_encoded_structures( $schema, $arguments );
		$arguments = $this->without_undeclared( $schema, $arguments );

		$wrong_shape = self::wrong_shape( $schema, $arguments, 'arguments' );
		if ( null !== $wrong_shape ) {
			return new \WP_Error( 'counterhand_wrong_shape', $wrong_shape );
		}

		$schema    = self::with_explicit_types( $schema );
		$sanitized = rest_sanitize_value_from_schema( $arguments, $schema, 'arguments' );
		if ( is_wp_error( $sanitized ) ) {
			return $sanitized;
		}

		$valid = rest_validate_value_from_schema( $sanitized, $schema, 'arguments' );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		return (array) $sanitized;
	}

	/**
	 * Checked before sanitizing, which turns a wrong shape into data: an object sent for
	 * a string becomes the word "Array", a string sent for an object becomes empty, and
	 * validating the result accepts both. Only shape: sanitizing should still turn the
	 * number a model sends for a price into the string the field declares.
	 *
	 * @param array<string, mixed> $schema
	 */
	private static function wrong_shape( array $schema, mixed $value, string $path ): ?string {
		$types = (array) ( $schema['type'] ?? [] );

		if ( [] === $types || null === $value ) {
			return null;
		}

		$takes_structure = [] !== array_intersect( $types, [ 'array', 'object' ] );

		if ( ! is_array( $value ) ) {
			// Not arrays: core reads "1,2,3" as a list, and models send ids that way.
			return [] === array_diff( $types, [ 'object', 'null' ] )
				? sprintf( '%s must be %s.', $path, implode( ' or ', $types ) )
				: null;
		}

		if ( ! $takes_structure ) {
			return sprintf( '%s must be %s, not a list or object.', $path, implode( ' or ', $types ) );
		}

		foreach ( $value as $key => $item ) {
			$item_schema = array_is_list( $value ) ? ( $schema['items'] ?? null ) : ( $schema['properties'][ $key ] ?? null );
			$problem     = is_array( $item_schema ) ? self::wrong_shape( $item_schema, $item, $path . '[' . $key . ']' ) : null;

			if ( null !== $problem ) {
				return $problem;
			}
		}

		return null;
	}

	/**
	 * A closed schema has always dropped what it does not name rather than refuse it,
	 * because models add stray arguments to calls that are otherwise right.
	 *
	 * @param  array<string, mixed> $schema
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function without_undeclared( array $schema, array $arguments ): array {
		if ( false !== ( $schema['additionalProperties'] ?? null ) || ! is_array( $schema['properties'] ?? null ) ) {
			return $arguments;
		}

		return array_intersect_key( $arguments, $schema['properties'] );
	}

	/**
	 * Models routinely send a structured argument as a JSON string and clients
	 * forward it verbatim, so a request whose intent was correct gets rejected.
	 *
	 * Coercing only where the schema declares array or object is the guard that
	 * matters: the MCP Python SDK decoded any JSON-looking string and broke the
	 * tools that wanted one.
	 *
	 * @param  array<string, mixed> $schema
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function parse_encoded_structures( array $schema, array $arguments ): array {
		$properties = $schema['properties'] ?? [];

		if ( ! is_array( $properties ) ) {
			return $arguments;
		}

		foreach ( $arguments as $name => $value ) {
			if ( ! is_string( $value ) || ! is_array( $properties[ $name ] ?? null ) ) {
				continue;
			}

			if ( [] === array_intersect( (array) ( $properties[ $name ]['type'] ?? [] ), [ 'array', 'object' ] ) ) {
				continue;
			}

			$decoded = json_decode( $value, true );

			if ( is_array( $decoded ) ) {
				$arguments[ $name ] = $decoded;
			}
		}

		return $arguments;
	}

	private function apply_schema_defaults( array $schema, array $arguments ): array {
		$properties = $schema['properties'] ?? [];
		if ( ! is_array( $properties ) ) {
			return $arguments;
		}

		foreach ( $properties as $property_name => $property_schema ) {
			if ( array_key_exists( $property_name, $arguments ) || ! is_array( $property_schema ) || ! array_key_exists( 'default', $property_schema ) ) {
				continue;
			}

			// A null default means "unset", and sending null fails a format check
			// that omitting the key passes.
			if ( null === $property_schema['default'] ) {
				continue;
			}

			$arguments[ $property_name ] = $property_schema['default'];
		}

		return $arguments;
	}
}
