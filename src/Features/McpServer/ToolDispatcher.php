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

	private const NESTED_SCHEMAS = [ 'items', 'additionalProperties' ];

	private const SCHEMA_LISTS = [ 'anyOf', 'oneOf', 'allOf' ];

	public function __construct( private ToolRegistry $registry ) {}

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

		$validated = $arguments;

		try {
			$validated = $this->validate_arguments( $tool, $arguments );
			if ( is_wp_error( $validated ) ) {
				return DispatchOutcome::rejected( $validated->get_error_message() );
			}

			// Defense in depth: the registry already filtered by scope, but the
			// gate inside the dispatch path must hold on its own.
			$agent->require_scope( $tool->required_scope() );

			$data = $tool->execute( $this->confirmed_arguments( $tool, $validated ) );

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
	 * The tool never sees `confirm`: it is this pipeline's argument, and sending it
	 * on would be an unknown parameter to whatever the tool wraps.
	 *
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 * @throws ToolCallException When a call needing confirmation does not carry confirm set to true.
	 */
	private function confirmed_arguments( ToolInterface $tool, array $arguments ): array {
		if ( ! $tool->requires_confirmation() ) {
			return $arguments;
		}

		if ( true !== ( $arguments[ self::CONFIRM_ARGUMENT ] ?? false ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException(
				'This changes the store in a way that is not easily undone. Tell the user exactly what will happen, and call it again with confirm set to true only once they have agreed.'
			);
		}

		unset( $arguments[ self::CONFIRM_ARGUMENT ] );

		return $arguments;
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
