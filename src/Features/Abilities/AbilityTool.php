<?php

declare( strict_types=1 );

namespace Counterhand\Features\Abilities;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Any exposed WordPress ability, as an MCP tool.
 *
 * A destructive ability needs confirmation like any other risky write, because
 * nothing here knows what third-party code will do beyond what it declares.
 */
final readonly class AbilityTool implements ToolInterface {

	private const MAX_NAME_LENGTH = 64;

	private const WRAPPED_ARGUMENT = 'input';

	private AbilityAnnotations $annotations;

	public function __construct(
		private \WP_Ability $ability,
		private IntegrationGroup $group,
	) {
		$this->annotations = AbilityAnnotations::of( $ability );
	}

	/** Slashes are not legal in a tool name for MCP clients or chat providers. */
	public function name(): string {
		$source = $this->ability->get_name();
		$name   = (string) preg_replace( '/^(\d)/', 'ability_$1', strtr( $source, '/-', '__' ) );

		return strlen( $name ) <= self::MAX_NAME_LENGTH
			? $name
			: substr( $name, 0, self::MAX_NAME_LENGTH - 9 ) . '_' . substr( md5( $source ), 0, 8 );
	}

	public function description(): string {
		return $this->ability->get_description();
	}

	/**
	 * What MCP requires an inputSchema to be, whatever the ability declared: it may
	 * be a scalar, or absent. An empty PHP array would encode as [] and strict
	 * clients reject that.
	 */
	public function input_schema(): array {
		$schema = $this->ability->get_input_schema();

		if ( $this->wraps_input( $schema ) ) {
			return [
				'type'       => 'object',
				'properties' => [ self::WRAPPED_ARGUMENT => $schema ],
				'required'   => array_key_exists( 'default', $schema ) ? [] : [ self::WRAPPED_ARGUMENT ],
			];
		}

		$schema['type'] = 'object';

		if ( empty( $schema['properties'] ) ) {
			$schema['properties'] = new \stdClass();
		}

		return $schema;
	}

	public function required_scope(): ScopeInterface {
		return $this->annotations->read_only ? $this->group->read_scope() : ( $this->group->write_scope() ?? $this->group->read_scope() );
	}

	public function group(): IntegrationGroup {
		return $this->group;
	}

	/**
	 * Never probes an ability that needs input: permission often depends on it
	 * (wpforms/get-form refuses when no form is named), and execution checks it
	 * with the real input anyway.
	 */
	public function is_available(): bool {
		if ( [] !== ( $this->input_schema()['required'] ?? [] ) ) {
			return true;
		}

		try {
			return true === $this->ability->check_permissions();
		} catch ( \Throwable ) {
			return false;
		}
	}

	public function requires_confirmation(): bool {
		return $this->annotations->destructive;
	}

	public function is_read_only(): bool {
		return $this->annotations->read_only;
	}

	public function is_destructive(): bool {
		return $this->annotations->destructive;
	}

	public function is_idempotent(): bool {
		return $this->annotations->idempotent;
	}

	/** Nothing here knows what third-party code reaches, so MCP's unsafe default stands. */
	public function is_open_world(): bool {
		return true;
	}

	public function execute( array $arguments ): array {
		$result = $this->ability->execute( $this->ability_input( $arguments ) );

		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( $result->get_error_message() );
		}

		// structuredContent must be a JSON object, and a list or a scalar is not one.
		return is_array( $result ) && ! array_is_list( $result ) ? $result : [ 'result' => $result ];
	}

	/** @param array<string, mixed> $schema */
	private function wraps_input( array $schema ): bool {
		return [] !== $schema && 'object' !== ( $schema['type'] ?? null ) && ! isset( $schema['properties'] );
	}

	/**
	 * Core rejects any input at all for an ability declaring no schema.
	 *
	 * @param array<string, mixed> $arguments
	 */
	private function ability_input( array $arguments ): mixed {
		$schema = $this->ability->get_input_schema();

		if ( [] === $schema ) {
			return null;
		}

		return $this->wraps_input( $schema ) ? ( $arguments[ self::WRAPPED_ARGUMENT ] ?? null ) : $arguments;
	}
}
