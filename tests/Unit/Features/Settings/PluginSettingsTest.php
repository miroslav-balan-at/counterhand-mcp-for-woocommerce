<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Settings;

use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * Pins the option contract before defaults() is made computed: every group has
 * a read key, the stored row only ever overlays the defaults, and a group with
 * no write key reads as write-disabled rather than fatalling.
 */
final class PluginSettingsTest extends TestCase {

	public function test_defaults_cover_a_read_key_for_every_group(): void {
		$defaults = PluginSettings::defaults();

		foreach ( ToolGroup::cases() as $group ) {
			$this->assertArrayHasKey( $group->value . '_read', $defaults );
		}
	}

	public function test_shipped_defaults_are_conservative(): void {
		$defaults = PluginSettings::defaults();

		$this->assertFalse( $defaults['enabled'] );
		$this->assertFalse( $defaults['action_log_enabled'] );

		foreach ( array_keys( $defaults ) as $key ) {
			if ( str_ends_with( (string) $key, '_write' ) ) {
				$this->assertFalse( $defaults[ $key ], sprintf( 'Write axis "%s" must ship off.', $key ) );
			}
		}
	}

	public function test_stored_values_overlay_defaults_without_dropping_keys(): void {
		Functions\when( 'get_option' )->justReturn( [ 'enabled' => true ] );

		$all = ( new PluginSettings() )->all();

		$this->assertTrue( $all['enabled'] );
		$this->assertSame( PluginSettings::defaults()['rate_limit_per_minute'], $all['rate_limit_per_minute'] );
	}

	public function test_a_corrupt_option_row_falls_back_to_defaults(): void {
		Functions\when( 'get_option' )->justReturn( 'not-an-array' );

		$this->assertSame( PluginSettings::defaults(), ( new PluginSettings() )->all() );
	}

	public function test_group_without_a_write_key_reads_as_write_disabled(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$this->assertFalse( ( new PluginSettings() )->is_group_write_enabled( ToolGroup::Reports ) );
	}

	public function test_group_toggles_read_their_own_axis(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'products_read'  => true,
				'products_write' => false,
			]
		);

		$settings = new PluginSettings();

		$this->assertTrue( $settings->is_group_read_enabled( ToolGroup::Products ) );
		$this->assertFalse( $settings->is_group_write_enabled( ToolGroup::Products ) );
	}

	public function test_numeric_getters_clamp_to_at_least_one(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'rate_limit_per_minute' => 0,
				'log_retention_days'    => -5,
			]
		);

		$settings = new PluginSettings();

		$this->assertSame( 1, $settings->rate_limit_per_minute() );
		$this->assertSame( 1, $settings->log_retention_days() );
	}

	/**
	 * The payload the const used to hold, spelled out. Computing defaults() had
	 * to change how the array is built without changing a byte of it: keys and
	 * their order both matter, because update_option() compares the new payload
	 * against the stored one with === and a reordered array is a spurious write
	 * on every existing install.
	 *
	 * Reports and Customers appear with a read key only — they have no :write
	 * scope, so they must not gain a write key they would never honour.
	 */
	public function test_defaults_payload_is_unchanged_in_keys_order_and_values(): void {
		$this->assertSame(
			[
				'enabled'               => false,
				'products_read'         => true,
				'products_write'        => false,
				'orders_read'           => true,
				'orders_write'          => false,
				'customers_read'        => false,
				'reports_read'          => true,
				'coupons_read'          => false,
				'coupons_write'         => false,
				'taxonomy_read'         => false,
				'taxonomy_write'        => false,
				'variations_read'       => false,
				'variations_write'      => false,
				'reviews_read'          => false,
				'reviews_write'         => false,
				'refunds_read'          => false,
				'refunds_write'         => false,
				'shipping_read'         => false,
				'shipping_write'        => false,
				'taxes_read'            => false,
				'taxes_write'           => false,
				'data_read'             => false,
				'gateways_read'         => false,
				'gateways_write'        => false,
				'settings_read'         => false,
				'settings_write'        => false,
				'content_read'          => false,
				'content_write'         => false,
				'system_read'           => false,
				'system_write'          => false,
				'rate_limit_per_minute' => 60,
				'action_log_enabled'    => false,
				'log_retention_days'    => 30,
			],
			PluginSettings::defaults()
		);
	}

	/**
	 * A read-only group gaining a write key would render a write checkbox the
	 * scope catalog cannot back.
	 */
	public function test_only_groups_with_a_write_scope_get_a_write_key(): void {
		$defaults = PluginSettings::defaults();

		foreach ( ToolGroup::cases() as $group ) {
			$this->assertSame(
				$group->has_write(),
				array_key_exists( $group->write_option_key(), $defaults ),
				sprintf( 'Group "%s" disagrees with its own write axis.', $group->value )
			);
		}
	}

	/** Read defaults come from the group itself, not a second list to keep in step. */
	public function test_read_defaults_follow_the_group_taxonomy(): void {
		$defaults = PluginSettings::defaults();

		foreach ( ToolGroup::cases() as $group ) {
			$this->assertSame(
				$group->enabled_by_default(),
				$defaults[ $group->read_option_key() ],
				sprintf( 'Group "%s" ships with the wrong read default.', $group->value )
			);
		}
	}
}
