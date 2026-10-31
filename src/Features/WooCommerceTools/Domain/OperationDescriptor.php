<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * One tool, stated as what it does rather than how it is done.
 *
 * Deliberately absent: the HTTP method, the route, the scope and the input
 * schema. The first two follow from the Operation, the third from the
 * operation's intent and the resource's group, and the fourth is asked of
 * WooCommerce at runtime. Anything restated here is something that can drift.
 */
final readonly class OperationDescriptor {

	/**
	 * @param FieldProfile         $fields               Which arguments to publish and which fields to ask back.
	 * @param string               $hint                 Resource-specific sentence appended to the generated description.
	 * @param string|null          $description_override Replaces the generated description outright.
	 * @param array<string, mixed> $forced_params        Params applied after the agent's, so they cannot be overridden.
	 * @param array<string, mixed> $default_params       Params applied before the agent's, overriding WooCommerce's own default.
	 * @param bool                 $requires_confirmation Whether the tool publishes a required `confirm` argument.
	 * @param ArgumentPolicy|null  $policy               A rule about what the arguments may say, checked before dispatch.
	 * @param string|null          $body_argument        Argument whose value is the whole JSON request body, for the
	 *                                                   controllers that read get_json_params() instead of params.
	 * @param array<string, mixed>|null $body_schema     JSON Schema for that argument. Hand-written because the route
	 *                                                   publishes no argument to derive it from.
	 * @param array<string, array<string, mixed>> $declared_arguments Argument name => JSON Schema, only for a route that publishes none. The one permitted copy of a schema: there is nothing live to derive from.
	 */
	public function __construct(
		public ToolName $name,
		public Operation $operation,
		public FieldProfile $fields,
		public string $hint = '',
		public ?string $description_override = null,
		public array $forced_params = [],
		public array $default_params = [],
		public bool $requires_confirmation = false,
		public ?ArgumentPolicy $policy = null,
		public ?string $body_argument = null,
		public ?array $body_schema = null,
		public array $declared_arguments = [],
	) {}

	/**
	 * Adds what this operation declares to a schema derived from the route. The
	 * live route wins on any name, so a plugin that starts declaring its arguments
	 * takes over from this copy with no edit here.
	 *
	 * @param  array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	public function with_declared_arguments( array $schema ): array {
		if ( [] === $this->declared_arguments ) {
			return $schema;
		}

		$schema['properties'] = [ ...$this->declared_arguments, ...( $schema['properties'] ?? [] ) ];

		return $schema;
	}

	/**
	 * A risky write takes only what it declares. Declared arguments are the
	 * exception: they are a copy, so what a plugin adds later has to get through.
	 */
	public function accepts_additional(): bool {
		return $this->fields->allow_additional && ( ! $this->requires_confirmation || [] !== $this->declared_arguments );
	}

	public function describe( string $singular, string $plural ): string {
		if ( null !== $this->description_override ) {
			return $this->description_override;
		}

		return trim( $this->operation->describe( $singular, $plural ) . ' ' . $this->hint );
	}
}
