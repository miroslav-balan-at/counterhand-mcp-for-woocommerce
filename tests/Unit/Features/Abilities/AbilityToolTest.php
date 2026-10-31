<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Abilities;

use Brain\Monkey\Functions;
use Counterhand\Features\Abilities\AbilityTool;
use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Tests\Unit\TestCase;

final class AbilityToolTest extends TestCase {

	private const READ_ONLY = [
		'annotations' => [
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		],
	];

	private const UNKNOWN_DANGER = [
		'annotations' => [
			'readonly'    => false,
			'destructive' => null,
			'idempotent'  => null,
		],
	];

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
	}

	private function tool( \WP_Ability $ability ): AbilityTool {
		$group = IntegrationGroup::try_of( explode( '/', $ability->get_name() )[0] );
		$this->assertNotNull( $group );

		return new AbilityTool( $ability, $group );
	}

	public function test_the_name_is_legal_for_every_client_and_keeps_its_namespace(): void {
		$this->assertSame( 'yoast_seo_get_post_seo_data', $this->tool( new \WP_Ability( 'yoast-seo/get-post-seo-data' ) )->name() );
	}

	public function test_a_name_starting_with_a_digit_gets_a_leading_letter(): void {
		$this->assertSame( 'ability_1password_get_item', $this->tool( new \WP_Ability( '1password/get-item' ) )->name() );
	}

	public function test_a_long_name_stays_within_the_log_column_and_stays_distinct(): void {
		$first  = $this->tool( new \WP_Ability( 'some-plugin/' . str_repeat( 'a', 70 ) . '-one' ) )->name();
		$second = $this->tool( new \WP_Ability( 'some-plugin/' . str_repeat( 'a', 70 ) . '-two' ) )->name();

		$this->assertLessThanOrEqual( 64, strlen( $first ) );
		$this->assertNotSame( $first, $second );
	}

	public function test_null_annotations_read_the_way_mcp_defaults_do(): void {
		$tool = $this->tool( new \WP_Ability( 'yoast-seo/update', [], self::UNKNOWN_DANGER ) );

		$this->assertFalse( $tool->is_read_only() );
		$this->assertTrue( $tool->is_destructive() );
		$this->assertFalse( $tool->is_idempotent() );
		$this->assertTrue( $tool->requires_confirmation() );
	}

	/** A plugin's label about itself does not decide whether a person gets asked. */
	public function test_a_write_declared_non_destructive_still_needs_confirmation(): void {
		$tool = $this->tool(
			new \WP_Ability(
				'rank-math/set-global-seo-settings',
				[],
				[
					'annotations' => [
						'readonly'    => false,
						'destructive' => false,
					],
				]
			)
		);

		$this->assertTrue( $tool->requires_confirmation() );
		$this->assertSame( 'integration:rank-math:write', $tool->required_scope()->key() );
	}

	public function test_a_read_needs_no_confirmation(): void {
		$this->assertFalse( $this->tool( new \WP_Ability( 'wpforms/list', [], self::READ_ONLY ) )->requires_confirmation() );
	}

	public function test_a_read_only_ability_is_gated_by_the_read_scope(): void {
		$this->assertSame( 'integration:wpforms:read', $this->tool( new \WP_Ability( 'wpforms/list', [], self::READ_ONLY ) )->required_scope()->key() );
	}

	public function test_an_absent_schema_becomes_an_empty_object_and_sends_no_input(): void {
		$ability = new \WP_Ability( 'some-plugin/get-info', [], self::READ_ONLY );
		$tool    = $this->tool( $ability );

		$this->assertSame( 'object', $tool->input_schema()['type'] );
		$this->assertSame( [], $tool->input_schema()['properties'] );

		$tool->execute( [] );

		$this->assertSame( [ null ], $ability->executed_with );
	}

	public function test_a_scalar_schema_is_wrapped_and_unwrapped(): void {
		$ability = new \WP_Ability( 'some-plugin/shout', [ 'type' => 'string' ], self::READ_ONLY );
		$tool    = $this->tool( $ability );

		$this->assertSame( [ 'input' ], $tool->input_schema()['required'] );

		$tool->execute( [ 'input' => 'hello' ] );

		$this->assertSame( [ 'hello' ], $ability->executed_with );
	}

	public function test_a_bridged_tool_never_promises_a_closed_world(): void {
		$this->assertTrue( $this->tool( new \WP_Ability( 'some-plugin/get', [], self::READ_ONLY ) )->is_open_world() );
	}

	public function test_an_error_from_the_ability_reaches_the_agent(): void {
		$this->expectException( ToolCallException::class );
		$this->expectExceptionMessage( 'Nope' );

		$this->tool( new \WP_Ability( 'some-plugin/fail', [], self::READ_ONLY, new \WP_Error( 'nope', 'Nope' ) ) )->execute( [] );
	}

	/** @dataProvider wrapped_results */
	public function test_anything_but_an_object_is_wrapped_for_structured_content( mixed $result ): void {
		$this->assertSame( [ 'result' => $result ], $this->tool( new \WP_Ability( 'some-plugin/get', [], self::READ_ONLY, $result ) )->execute( [] ) );
	}

	/** @return iterable<string, array{mixed}> */
	public static function wrapped_results(): iterable {
		yield 'list' => [ [ 'a', 'b' ] ];
		yield 'empty' => [ [] ];
		yield 'scalar' => [ 'text' ];
		yield 'null' => [ null ];
	}

	public function test_an_object_result_passes_through(): void {
		$this->assertSame( [ 'id' => 1 ], $this->tool( new \WP_Ability( 'some-plugin/get', [], self::READ_ONLY, [ 'id' => 1 ] ) )->execute( [] ) );
	}

	public function test_an_ability_needing_input_is_not_probed_at_list_time(): void {
		$tool = $this->tool(
			new \WP_Ability(
				'wpforms/get-form',
				[
					'type'     => 'object',
					'required' => [ 'form_id' ],
				],
				self::READ_ONLY,
				[],
				false
			)
		);

		$this->assertTrue( $tool->is_available() );
	}

	/** @dataProvider unavailable_permissions */
	public function test_an_inputless_ability_is_hidden_unless_it_answers_exactly_true( mixed $permission ): void {
		$this->assertFalse( $this->tool( new \WP_Ability( 'some-plugin/get', [], self::READ_ONLY, [], $permission ) )->is_available() );
	}

	/** @return iterable<string, array{mixed}> */
	public static function unavailable_permissions(): iterable {
		yield 'denied' => [ false ];
		yield 'a value that is not true' => [ 'yes' ];
		yield 'a plugin bug must not break tools/list' => [ new \TypeError( 'boom' ) ];
	}

	/** Both spellings core accepts, so an item-level ability is never probed without its id. */
	public function test_a_per_property_required_counts_and_is_not_probed(): void {
		$tool = $this->tool(
			new \WP_Ability(
				'some-plugin/get',
				[
					'type'       => 'object',
					'properties' => [
						'id' => [
							'type'     => 'integer',
							'required' => true,
						],
					],
				],
				self::READ_ONLY,
				[],
				false
			)
		);

		$this->assertSame( [ 'id' ], $tool->input_schema()['required'] );
		$this->assertTrue( $tool->is_available() );
	}

	public function test_a_malformed_required_never_reaches_the_schema(): void {
		$schema = $this->tool(
			new \WP_Ability(
				'some-plugin/update',
				[
					'type'       => 'object',
					'required'   => true,
					'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				],
				self::UNKNOWN_DANGER
			)
		)->input_schema();

		$this->assertArrayNotHasKey( 'required', $schema );
	}

	/** Rank Math reports failure as a normal result, which would log as a success. */
	public function test_a_failure_reported_inside_the_result_is_a_failure(): void {
		$this->expectException( ToolCallException::class );
		$this->expectExceptionMessage( 'Unknown module' );

		$this->tool( new \WP_Ability( 'rank-math/set', [], self::READ_ONLY, [ 'error' => [ 'message' => 'Unknown module' ] ] ) )->execute( [] );
	}

	public function test_an_empty_error_key_is_not_a_failure(): void {
		$this->assertSame(
			[
				'error' => null,
				'ok'    => true,
			],
			$this->tool(
				new \WP_Ability(
					'rank-math/analyze',
					[],
					self::READ_ONLY,
					[
						'error' => null,
						'ok'    => true,
					]
				)
			)->execute( [] )
		);
	}

	public function test_a_rejected_answer_after_a_write_warns_against_a_blind_retry(): void {
		$this->expectExceptionMessage( 'may already have been made' );

		$this->tool( new \WP_Ability( 'some-plugin/save', [], self::UNKNOWN_DANGER, new \WP_Error( 'ability_invalid_output', 'Bad output' ) ) )->execute( [] );
	}

	public function test_a_policy_stops_the_call_before_the_ability_runs(): void {
		$ability = new \WP_Ability( 'yoast-seo/update-post-seo-data', [], self::UNKNOWN_DANGER );
		$tool    = new AbilityTool( $ability, IntegrationGroup::of( 'yoast-seo' ), new \Counterhand\Features\Abilities\YoastFieldPolicy() );

		try {
			$tool->execute( [ 'seo_title' => '%%cf__secret%%' ] );
			$this->fail( 'The variable should have been refused.' );
		} catch ( ToolCallException ) {
			$this->assertSame( [], $ability->executed_with );
		}
	}

	public function test_the_upsell_yoast_asks_a_model_to_relay_is_dropped(): void {
		$result = $this->tool(
			new \WP_Ability(
				'yoast-seo/update',
				[],
				self::READ_ONLY,
				[
					'id'               => 1,
					'ai_generate_hint' => 'Upgrade',
				]
			)
		)->execute( [] );

		$this->assertSame( [ 'id' => 1 ], $result );
	}

	public function test_every_write_reports_destructive_whatever_it_declares(): void {
		$tool = $this->tool(
			new \WP_Ability(
				'rank-math/set',
				[],
				[
					'annotations' => [
						'readonly'    => false,
						'destructive' => false,
					],
				]
			)
		);

		$this->assertTrue( $tool->is_destructive() );
	}

	public function test_a_default_on_the_root_is_not_published(): void {
		$schema = $this->tool(
			new \WP_Ability(
				'some-plugin/get',
				[
					'type'    => 'object',
					'default' => [],
				],
				self::READ_ONLY
			)
		)->input_schema();

		$this->assertArrayNotHasKey( 'default', $schema );
	}
}
