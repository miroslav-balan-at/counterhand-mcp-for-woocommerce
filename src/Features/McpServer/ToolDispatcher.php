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

	public function __construct( private ToolRegistry $registry ) {}

	/** @return list<ToolInterface> */
	public function visible_for( AuthenticatedAgent $agent ): array {
		return $this->registry->visible_for( $agent );
	}

	/** @return array<string, int> */
	public function tool_counts_by_group(): array {
		return $this->registry->tool_counts_by_group();
	}

	public function dispatch( string $tool_name, array $arguments, AuthenticatedAgent $agent ): DispatchOutcome {
		// Resolving through the agent-filtered set makes disabled or
		// out-of-scope tools indistinguishable from nonexistent ones.
		$tool = $this->registry->resolve_for( $agent, $tool_name );
		if ( null === $tool ) {
			return DispatchOutcome::rejected( sprintf( 'Unknown tool "%s".', $tool_name ) );
		}

		$validated = $this->validate_arguments( $tool, $arguments );
		if ( is_wp_error( $validated ) ) {
			return DispatchOutcome::rejected( $validated->get_error_message() );
		}

		try {
			// Defense in depth: the registry already filtered by scope, but the
			// gate inside the dispatch path must hold on its own.
			$agent->require_scope( $tool->required_scope() );

			$data = $tool->execute( $validated );

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
			do_action( 'ctrh_tool_called', $tool->name(), $agent->token->label, false, $validated, $tool->group()->value );

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
	 * A failed call is still a call: it is audited like a successful one, and it
	 * comes back as an outcome the agent can read and self-correct from.
	 *
	 * @param array<string, mixed> $arguments Validated arguments, as executed.
	 */
	private function failure( ToolInterface $tool, AuthenticatedAgent $agent, array $arguments, string $message ): DispatchOutcome {
		/** This hook is documented above. */
		do_action( 'ctrh_tool_called', $tool->name(), $agent->token->label, true, $arguments, $tool->group()->value );

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
		$schema = $tool->input_schema();

		// rest_sanitize/validate_value_from_schema do NOT apply defaults —
		// without this, absent args fall through to WooCommerce's own defaults
		// (e.g. create_product would publish instead of draft).
		$arguments = $this->apply_schema_defaults( $schema, $arguments );

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

	private function apply_schema_defaults( array $schema, array $arguments ): array {
		$properties = $schema['properties'] ?? [];
		if ( ! is_array( $properties ) ) {
			return $arguments;
		}

		foreach ( $properties as $property_name => $property_schema ) {
			if ( ! array_key_exists( $property_name, $arguments ) && is_array( $property_schema ) && array_key_exists( 'default', $property_schema ) ) {
				$arguments[ $property_name ] = $property_schema['default'];
			}
		}

		return $arguments;
	}
}
