<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Settings;

use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Settings\SettingSanitizer;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Unit\TestCase;

/**
 * This runs on every settings save, against whatever was posted. An unchecked
 * checkbox posts nothing at all, so "absent" has to mean off — if it meant
 * "leave as is", a store owner could never switch a group back off.
 */
final class SettingSanitizerTest extends TestCase {

	private SettingSanitizer $sanitizer;

	protected function setUp(): void {
		parent::setUp();
		$this->sanitizer = new SettingSanitizer();
	}

	public function test_output_carries_exactly_the_known_keys(): void {
		$this->assertSame(
			array_keys( PluginSettings::defaults() ),
			array_keys( $this->sanitizer->sanitize( [] ) )
		);
	}

	/** A key the form never advertised must not reach the option row. */
	public function test_unknown_keys_are_dropped(): void {
		$sanitized = $this->sanitizer->sanitize(
			[
				'enabled'          => '1',
				'is_admin'         => '1',
				'products_execute' => '1',
			]
		);

		$this->assertArrayNotHasKey( 'is_admin', $sanitized );
		$this->assertArrayNotHasKey( 'products_execute', $sanitized );
	}

	public function test_an_absent_checkbox_switches_its_group_off(): void {
		$sanitized = $this->sanitizer->sanitize( [ 'orders_read' => '1' ] );

		$this->assertTrue( $sanitized['orders_read'] );
		$this->assertFalse( $sanitized['products_read'] );
		$this->assertFalse( $sanitized['enabled'] );
	}

	/** Checkboxes post the string "1", never a real bool. */
	public function test_checkbox_values_are_stored_as_booleans(): void {
		$sanitized = $this->sanitizer->sanitize( [ 'enabled' => '1' ] );

		$this->assertTrue( $sanitized['enabled'] );
		$this->assertIsBool( $sanitized['enabled'] );
		$this->assertIsBool( $sanitized['products_write'] );
	}

	/** @dataProvider out_of_range_numbers */
	public function test_numbers_are_clamped_to_the_range_the_form_advertises(
		string $key,
		mixed $posted,
		int $expected
	): void {
		$this->assertSame( $expected, $this->sanitizer->sanitize( [ $key => $posted ] )[ $key ] );
	}

	/** @return iterable<string, array{string, mixed, int}> */
	public static function out_of_range_numbers(): iterable {
		yield 'rate limit below the floor'  => [ 'rate_limit_per_minute', '0', 1 ];
		yield 'rate limit above the ceiling' => [ 'rate_limit_per_minute', '99999', 1000 ];
		yield 'negative rate limit'          => [ 'rate_limit_per_minute', '-20', 1 ];
		yield 'retention above the ceiling'  => [ 'log_retention_days', '4000', 365 ];
		yield 'non-numeric retention'        => [ 'log_retention_days', 'forever', 1 ];
		yield 'retention within range'       => [ 'log_retention_days', '90', 90 ];
	}

	/** Nothing posted at all still has to yield a complete, valid payload. */
	public function test_a_non_array_payload_sanitizes_to_shipped_defaults(): void {
		$this->assertSame( $this->sanitizer->sanitize( [] ), $this->sanitizer->sanitize( 'not-an-array' ) );
		$this->assertSame( $this->sanitizer->sanitize( [] ), $this->sanitizer->sanitize( null ) );
	}

	/**
	 * The sanitizer is driven by defaults(), so a group added to the taxonomy is
	 * accepted with no edit here. That is the property worth pinning — it is the
	 * reason this class has no per-group knowledge in it.
	 */
	public function test_every_group_axis_survives_a_round_trip(): void {
		$posted = [];

		foreach ( ToolGroup::cases() as $group ) {
			$posted[ $group->read_option_key() ] = '1';

			if ( $group->has_write() ) {
				$posted[ $group->write_option_key() ] = '1';
			}
		}

		$sanitized = $this->sanitizer->sanitize( $posted );

		foreach ( ToolGroup::cases() as $group ) {
			$this->assertTrue(
				$sanitized[ $group->read_option_key() ],
				sprintf( 'Group "%s" lost its read toggle on save.', $group->value )
			);
		}
	}
}
