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
	) {}

	public function describe( string $singular, string $plural ): string {
		if ( null !== $this->description_override ) {
			return $this->description_override;
		}

		return trim( $this->operation->describe( $singular, $plural ) . ' ' . $this->hint );
	}
}
