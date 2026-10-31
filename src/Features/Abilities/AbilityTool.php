<?php

declare( strict_types=1 );

namespace Counterhand\Features\Abilities;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Features\WooCommerceTools\Domain\ArgumentPolicy;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Any exposed WordPress ability, as an MCP tool.
 */
final readonly class AbilityTool implements ToolInterface {

	private const MAX_NAME_LENGTH = 64;

	private const WRAPPED_ARGUMENT = 'input';

	/** WPForms Pro returns visitors' IP addresses unless this says otherwise. */
	private const MASK_IP_FILTER = 'wpforms_abilities_mask_ip_address';

	/** An upsell Yoast asks the model to relay, not store data. */
	private const DROPPED_RESULT_KEYS = [ 'ai_generate_hint' ];

	private AbilityAnnotations $annotations;

	public function __construct(
		private \WP_Ability $ability,
		private IntegrationGroup $group,
		private ?ArgumentPolicy $policy = null,
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
	 * be a scalar, or absent.
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

		unset( $schema['default'] );

		$schema['type']       = 'object';
		$schema['properties'] = is_array( $schema['properties'] ?? null ) ? $schema['properties'] : [];

		$required = $this->required_names( $schema );
		unset( $schema['required'] );

		if ( [] !== $required ) {
			$schema['required'] = $required;
		}

		return $schema;
	}

	/**
	 * Core accepts both spellings: a list on the object, and required: true on each
	 * property. Anything else in that slot is a mistake that must not reach a client.
	 *
	 * @param  array<string, mixed> $schema
	 * @return list<string>
	 */
	private function required_names( array $schema ): array {
		$listed = is_array( $schema['required'] ?? null ) ? $schema['required'] : [];

		foreach ( $schema['properties'] as $name => $property ) {
			if ( is_array( $property ) && true === ( $property['required'] ?? null ) ) {
				$listed[] = (string) $name;
			}
		}

		return array_values( array_unique( array_filter( $listed, 'is_string' ) ) );
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

	/**
	 * Every write, whatever it declares: Rank Math marks set-global-seo-settings and
	 * fix-site-seo destructive: false, and another plugin's label about itself must
	 * not decide whether a person gets asked.
	 */
	public function requires_confirmation(): bool {
		return ! $this->annotations->read_only;
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
		$verdict = $this->policy?->verdict( $arguments );

		if ( null !== $verdict && ! $verdict->allowed ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( $verdict->reason );
		}

		add_filter( self::MASK_IP_FILTER, '__return_true' );

		try {
			$result = $this->ability->execute( $this->ability_input( $arguments ) );
		} finally {
			remove_filter( self::MASK_IP_FILTER, '__return_true' );
		}

		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( $this->failure_message( $result ) );
		}

		$reported = is_array( $result ) ? self::reported_failure( $result ) : null;

		if ( null !== $reported ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( $reported );
		}

		// structuredContent must be a JSON object, and a list or a scalar is not one.
		return is_array( $result ) && ! array_is_list( $result )
			? array_diff_key( $result, array_flip( self::DROPPED_RESULT_KEYS ) )
			: [ 'result' => $result ];
	}

	/**
	 * Some plugins report failure as a normal result, Rank Math with error: {code, message},
	 * and it would otherwise reach the agent and the action log as a success. A string
	 * beside other data is not one: a log or status record can have a field called error.
	 *
	 * @param array<mixed> $result
	 */
	private static function reported_failure( array $result ): ?string {
		$error = $result['error'] ?? null;

		if ( is_array( $error ) && is_string( $error['message'] ?? null ) ) {
			return $error['message'];
		}

		if ( true === $error ) {
			return is_string( $result['message'] ?? null ) ? $result['message'] : 'The plugin reported a failure without saying why.';
		}

		return is_string( $error ) && '' !== $error && ( 1 === count( $result ) || false === ( $result['success'] ?? null ) ) ? $error : null;
	}

	/** Core checks the output after the callback ran, so this write may already have happened. */
	private function failure_message( \WP_Error $error ): string {
		return 'ability_invalid_output' === $error->get_error_code() && ! $this->annotations->read_only
			? 'The change may already have been made, but the plugin returned an answer that failed its own checks. Check the current state before trying again.'
			: $error->get_error_message();
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
