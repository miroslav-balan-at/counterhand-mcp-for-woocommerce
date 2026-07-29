<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\WooCommerceTools\Infrastructure;

use AgentGateMcp\Features\WooCommerceTools\Domain\FieldProfile;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestMethod;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RouteArgs;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\SchemaFromArgs;
use AgentGateMcp\Tests\Unit\TestCase;

/**
 * This is what every MCP client sees. A wrong schema is not a crash — it is a
 * tool an agent calls incorrectly, forever, with WooCommerce rejecting each
 * attempt. The argument shapes below are the real ones WooCommerce registers.
 */
final class SchemaFromArgsTest extends TestCase {

	private SchemaFromArgs $schema;

	protected function setUp(): void {
		parent::setUp();
		$this->schema = new SchemaFromArgs();
	}

	public function test_the_result_is_a_json_schema_object(): void {
		$built = $this->build( [ 'code' => [ 'type' => 'string' ] ] );

		$this->assertSame( 'object', $built['type'] );
		$this->assertSame( [ 'code' => [ 'type' => 'string' ] ], $built['properties'] );
	}

	public function test_an_argless_route_publishes_an_empty_property_set(): void {
		$built = $this->build( [] );

		$this->assertSame( [], $built['properties'] );
		$this->assertArrayNotHasKey( 'required', $built );
	}

	/**
	 * WordPress carries "required" as a sibling flag on each argument; JSON
	 * Schema wants one list on the object. This is the only genuine shape
	 * difference between the two formats.
	 */
	public function test_required_flags_are_hoisted_into_one_list(): void {
		$built = $this->build(
			[
				'id'   => [
					'type'     => 'integer',
					'required' => true,
				],
				'name' => [ 'type' => 'string' ],
				'sku'  => [
					'type'     => 'string',
					'required' => true,
				],
			]
		);

		$this->assertSame( [ 'id', 'sku' ], $built['required'] );
		$this->assertArrayNotHasKey( 'required', $built['properties']['id'] );
	}

	public function test_the_required_list_is_omitted_when_nothing_is_required(): void {
		$this->assertArrayNotHasKey( 'required', $this->build( [ 'name' => [ 'type' => 'string' ] ] ) );
	}

	/**
	 * Delegating to core's own whitelist is what keeps this from rotting: a
	 * keyword WordPress adds survives with no edit here, and callbacks —
	 * which are not schema and cannot be serialized to JSON — never leak.
	 */
	public function test_non_schema_keys_are_dropped(): void {
		$built = $this->build(
			[
				'status' => [
					'description'       => 'Coupon status.',
					'type'              => 'string',
					'enum'              => [ 'draft', 'publish' ],
					'default'           => 'publish',
					'context'           => [ 'view', 'edit' ],
					'readonly'          => true,
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'rest_sanitize_request_arg',
					'arg_options'       => [ 'sanitize_callback' => 'wc_clean' ],
				],
			]
		);

		$this->assertSame(
			[
				'description' => 'Coupon status.',
				'type'        => 'string',
				'enum'        => [ 'draft', 'publish' ],
				'default'     => 'publish',
			],
			$built['properties']['status']
		);
	}

	/** Kept on purpose: the server applies schema defaults before dispatching. */
	public function test_defaults_survive(): void {
		$built = $this->build(
			[
				'per_page' => [
					'type'    => 'integer',
					'default' => 10,
				],
			]
		);

		$this->assertSame( 10, $built['properties']['per_page']['default'] );
	}

	/**
	 * WooCommerce writes 'type' => 'mixed' on meta values and several order
	 * fields. It is not a JSON Schema type: strict clients reject the whole
	 * schema over it. Dropping the keyword leaves the value unconstrained,
	 * which is what "mixed" meant.
	 */
	public function test_a_type_json_schema_does_not_define_is_dropped(): void {
		$built = $this->build(
			[
				'value' => [
					'description' => 'Meta value.',
					'type'        => 'mixed',
				],
			]
		);

		$this->assertSame( [ 'description' => 'Meta value.' ], $built['properties']['value'] );
	}

	public function test_a_list_of_types_keeps_only_the_ones_json_schema_defines(): void {
		$built = $this->build( [ 'amount' => [ 'type' => [ 'string', 'mixed', 'number' ] ] ] );

		$this->assertSame( [ 'string', 'number' ], $built['properties']['amount']['type'] );
	}

	/** A one-member list is legal but noisy; strict clients read the string form more happily. */
	public function test_a_list_that_reduces_to_one_type_collapses_to_a_string(): void {
		$built = $this->build( [ 'amount' => [ 'type' => [ 'string', 'mixed' ] ] ] );

		$this->assertSame( 'string', $built['properties']['amount']['type'] );
	}

	/**
	 * The real product meta_data shape: an array of objects whose value is
	 * 'mixed'. Normalization has to reach it two levels down or the schema is
	 * still unusable.
	 */
	public function test_normalization_recurses_into_items_and_properties(): void {
		$built = $this->build(
			[
				'meta_data' => [
					'description' => 'Meta data.',
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'    => [
								'type'     => 'integer',
								'readonly' => true,
							],
							'key'   => [ 'type' => 'string' ],
							'value' => [ 'type' => 'mixed' ],
						],
					],
				],
			]
		);

		$this->assertSame(
			[
				'description' => 'Meta data.',
				'type'        => 'array',
				'items'       => [
					'type'       => 'object',
					'properties' => [
						'id'    => [ 'type' => 'integer' ],
						'key'   => [ 'type' => 'string' ],
						'value' => [],
					],
				],
			],
			$built['properties']['meta_data']
		);
	}

	public function test_normalization_recurses_into_alternatives(): void {
		$built = $this->build(
			[
				'date_created' => [
					'anyOf' => [
						[ 'type' => 'mixed' ],
						[
							'type'   => 'string',
							'format' => 'date-time',
						],
					],
				],
			]
		);

		$this->assertSame(
			[
				[],
				[
					'type'   => 'string',
					'format' => 'date-time',
				],
			],
			$built['properties']['date_created']['anyOf']
		);
	}

	public function test_a_schema_valued_additional_properties_is_normalized_too(): void {
		$built = $this->build(
			[
				'attributes' => [
					'type'                 => 'object',
					'additionalProperties' => [ 'type' => 'mixed' ],
				],
			]
		);

		$this->assertSame( [], $built['properties']['attributes']['additionalProperties'] );
	}

	public function test_a_boolean_additional_properties_is_left_alone(): void {
		$built = $this->build(
			[
				'attributes' => [
					'type'                 => 'object',
					'additionalProperties' => false,
				],
			]
		);

		$this->assertFalse( $built['properties']['attributes']['additionalProperties'] );
	}

	/**
	 * On a nested object "required" is already a list of names — valid JSON
	 * Schema, and worth keeping rather than losing to the same whitelist that
	 * correctly drops the top-level boolean form.
	 */
	public function test_a_nested_required_list_is_preserved(): void {
		$built = $this->build(
			[
				'billing' => [
					'type'       => 'object',
					'required'   => [ 'first_name', 'email' ],
					'properties' => [
						'first_name' => [ 'type' => 'string' ],
						'email'      => [ 'type' => 'string' ],
					],
				],
			]
		);

		$this->assertSame( [ 'first_name', 'email' ], $built['properties']['billing']['required'] );
		$this->assertArrayNotHasKey( 'required', $built );
	}

	/**
	 * These belong to the REST envelope, not to the tool. Exposing 'context'
	 * would let an agent ask for 'edit', which returns raw HTML and roughly
	 * doubles every payload; exposing '_fields' would let it silently defeat
	 * the response pruning the tool already does on its behalf.
	 *
	 * @dataProvider envelope_params
	 */
	public function test_envelope_params_never_reach_the_tool( string $param ): void {
		$built = $this->build(
			[
				$param     => [ 'type' => 'string' ],
				'per_page' => [ 'type' => 'integer' ],
			]
		);

		$this->assertSame( [ 'per_page' ], array_keys( $built['properties'] ) );
	}

	/** @return iterable<string, array{string}> */
	public static function envelope_params(): iterable {
		foreach ( [ 'context', '_fields', '_embed', '_links', '_method', '_envelope', '_jsonp', '_locale' ] as $param ) {
			yield $param => [ $param ];
		}
	}

	public function test_a_profile_prunes_to_the_fields_it_names(): void {
		$built = $this->build(
			[
				'code'          => [ 'type' => 'string' ],
				'amount'        => [ 'type' => 'string' ],
				'discount_type' => [ 'type' => 'string' ],
				'date_expires'  => [ 'type' => 'string' ],
			],
			new FieldProfile( [ 'code', 'amount' ], [] )
		);

		$this->assertSame( [ 'code', 'amount' ], array_keys( $built['properties'] ) );
	}

	/**
	 * A required argument the profile forgot would ship a tool no agent can
	 * ever call successfully — WooCommerce would reject every attempt. Required
	 * arguments are not the descriptor's to prune.
	 */
	public function test_a_required_field_survives_a_profile_that_omits_it(): void {
		$built = $this->build(
			[
				'id'   => [
					'type'     => 'integer',
					'required' => true,
				],
				'code' => [ 'type' => 'string' ],
				'note' => [ 'type' => 'string' ],
			],
			new FieldProfile( [ 'code' ], [] )
		);

		$this->assertSame( [ 'code', 'id' ], array_keys( $built['properties'] ) );
		$this->assertSame( [ 'id' ], $built['required'] );
	}

	/**
	 * Matching nothing at all is the signature of a WooCommerce release having
	 * rebuilt the controller, not of one field going away. A fat schema is
	 * worse than a curated one and far better than an empty one.
	 */
	public function test_a_profile_matching_nothing_falls_back_to_the_whole_schema(): void {
		$built = $this->build(
			[
				'code'   => [ 'type' => 'string' ],
				'amount' => [ 'type' => 'string' ],
			],
			new FieldProfile( [ 'renamed_away', 'also_gone' ], [] )
		);

		$this->assertSame( [ 'code', 'amount' ], array_keys( $built['properties'] ) );
	}

	public function test_writes_accept_fields_the_schema_does_not_name(): void {
		$built = $this->build( [ 'code' => [ 'type' => 'string' ] ], new FieldProfile( [ 'code' ], [], true ) );

		$this->assertTrue( $built['additionalProperties'] );
	}

	public function test_reads_refuse_fields_the_schema_does_not_name(): void {
		$built = $this->build( [ 'code' => [ 'type' => 'string' ] ], new FieldProfile( [ 'code' ], [], false ) );

		$this->assertFalse( $built['additionalProperties'] );
	}

	/** A few legacy routes register an argument as a bare string. There is no schema in that. */
	public function test_an_argument_that_is_not_a_spec_is_skipped(): void {
		$built = $this->build(
			[
				'legacy' => 'nonsense',
				'code'   => [ 'type' => 'string' ],
			]
		);

		$this->assertSame( [ 'code' ], array_keys( $built['properties'] ) );
	}

	/**
	 * WooCommerce marks the id optional and is right to: it reads it out of the
	 * URL, where it is structural rather than optional. An agent has only the
	 * argument list to go on, so omitting it has to be a schema violation it
	 * can see rather than a dispatch failure it cannot.
	 */
	public function test_a_path_parameter_is_published_as_required(): void {
		$built = $this->build(
			[
				'id'   => [ 'type' => 'integer' ],
				'code' => [ 'type' => 'string' ],
			],
			path_params: [ 'id' ]
		);

		$this->assertSame( [ 'id' ], $built['required'] );
	}

	/** Without it the tool could not name the resource it is meant to act on. */
	public function test_a_path_parameter_survives_a_profile_that_omits_it(): void {
		$built = $this->build(
			[
				'id'   => [ 'type' => 'integer' ],
				'code' => [ 'type' => 'string' ],
			],
			new FieldProfile( [ 'code' ], [] ),
			[ 'id' ]
		);

		$this->assertSame( [ 'code', 'id' ], array_keys( $built['properties'] ) );
		$this->assertSame( [ 'type' => 'integer' ], $built['properties']['id'] );
	}

	/**
	 * The route template still has the placeholder, so the value is not
	 * optional whatever the controller has stopped saying about it. A string is
	 * the least this can claim and stay true.
	 */
	public function test_a_path_parameter_woocommerce_no_longer_describes_is_still_published(): void {
		$built = $this->build( [ 'code' => [ 'type' => 'string' ] ], path_params: [ 'id' ] );

		$this->assertSame( 'string', $built['properties']['id']['type'] );
		$this->assertNotSame( '', $built['properties']['id']['description'] );
		$this->assertSame( [ 'id' ], $built['required'] );
	}

	public function test_a_path_parameter_woocommerce_also_marks_required_is_listed_once(): void {
		$built = $this->build(
			[
				'id' => [
					'type'     => 'integer',
					'required' => true,
				],
			],
			path_params: [ 'id' ]
		);

		$this->assertSame( [ 'id' ], $built['required'] );
	}

	/**
	 * @param array<string, mixed> $args
	 * @param list<string>         $path_params
	 * @return array<string, mixed>
	 */
	private function build( array $args, ?FieldProfile $fields = null, array $path_params = [] ): array {
		return $this->schema->build(
			new RouteArgs( '/wc/v3/coupons', RestMethod::Post, $args ),
			$fields ?? FieldProfile::everything(),
			$path_params
		);
	}
}
