<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\WooCommerceTools\Domain;

use AgentGateMcp\Features\WooCommerceTools\Domain\SecretSettingPolicy;
use AgentGateMcp\Features\WooCommerceTools\Domain\SystemToolPolicy;
use AgentGateMcp\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The rules about what a tool's arguments may say.
 *
 * WooCommerce gates both of these endpoints on manage_woocommerce, which every
 * token owner of this plugin already holds — so for settings writes and
 * maintenance tools, WooCommerce's own check draws no distinction at all
 * between changing the shop's postal address and overwriting a live payment
 * credential, or between clearing a cache and dropping the orders table. These
 * are where that distinction gets made.
 */
final class ArgumentPolicyTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	/** @dataProvider credential_ids */
	public function test_a_setting_named_like_a_credential_is_refused( string $id ): void {
		$verdict = ( new SecretSettingPolicy() )->verdict( [ 'id' => $id ] );

		$this->assertFalse( $verdict->allowed, $id . ' is writable.' );
		$this->assertStringContainsString( $id, $verdict->reason );
	}

	/** @return iterable<string, array{string}> */
	public static function credential_ids(): iterable {
		yield 'stripe secret'   => [ 'woocommerce_stripe_secret_key' ];
		yield 'publishable key' => [ 'woocommerce_stripe_publishable_key' ];
		yield 'api token'       => [ 'shipping_api_token' ];
		yield 'password'        => [ 'smtp_password' ];
		yield 'bare secret'     => [ 'secret' ];
		yield 'credentials'     => [ 'gateway_credentials' ];
	}

	/**
	 * Word-boundary matched, so ordinary settings whose names merely contain
	 * those letters are untouched. Getting this wrong would make the tool
	 * useless rather than unsafe, but useless is still wrong.
	 *
	 * @dataProvider ordinary_ids
	 */
	public function test_an_ordinary_setting_is_writable( string $id ): void {
		$this->assertTrue( ( new SecretSettingPolicy() )->verdict( [ 'id' => $id ] )->allowed, $id . ' was refused.' );
	}

	/** @return iterable<string, array{string}> */
	public static function ordinary_ids(): iterable {
		yield 'currency'  => [ 'woocommerce_currency' ];
		yield 'address'   => [ 'woocommerce_store_address' ];
		yield 'keyword'   => [ 'woocommerce_keyword_filter' ];
		yield 'monkey'    => [ 'monkey_setting' ];
		yield 'passenger' => [ 'passenger_count' ];
	}

	public function test_a_store_can_widen_or_narrow_the_setting_rule(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value, mixed $id = null ): mixed =>
				'agmcp_setting_writable' === $hook && 'our_key_visual' === $id ? true : $value
		);

		$this->assertTrue( ( new SecretSettingPolicy() )->verdict( [ 'id' => 'our_key_visual' ] )->allowed );
	}

	/**
	 * The maintenance tools a store cannot recover from without a backup.
	 *
	 * reset_roles in particular rewrites every user's capabilities, and the
	 * endpoint that runs it is gated only by a capability the caller already
	 * has — so nothing but this list stands in the way.
	 *
	 * @dataProvider irreversible_tools
	 */
	public function test_an_irreversible_maintenance_tool_is_refused( string $id ): void {
		$verdict = ( new SystemToolPolicy() )->verdict( [ 'id' => $id ] );

		$this->assertFalse( $verdict->allowed, $id . ' is runnable.' );
		$this->assertStringContainsString( 'cannot be undone', $verdict->reason );
	}

	/** @return iterable<string, array{string}> */
	public static function irreversible_tools(): iterable {
		yield 'reset_roles'                => [ 'reset_roles' ];
		yield 'delete_taxes'               => [ 'delete_taxes' ];
		yield 'db_update_routine'          => [ 'db_update_routine' ];
		yield 'delete_custom_orders_table' => [ 'delete_custom_orders_table' ];
		yield 'hpos_legacy_cleanup'        => [ 'hpos_legacy_cleanup' ];
		yield 'install_pages'              => [ 'install_pages' ];
	}

	/** Anything that only costs time to rebuild stays available, behind confirmation. */
	public function test_a_recoverable_maintenance_tool_is_allowed(): void {
		foreach ( [ 'clear_transients', 'recount_terms', 'regenerate_thumbnails', 'clear_sessions' ] as $id ) {
			$this->assertTrue( ( new SystemToolPolicy() )->verdict( [ 'id' => $id ] )->allowed, $id . ' was refused.' );
		}
	}

	/**
	 * A store's own plugins register maintenance tools too — the store this was
	 * developed against had one — so the filter has to be able to deny an id
	 * this list has never heard of.
	 */
	public function test_a_store_can_deny_a_maintenance_tool_of_its_own(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value, mixed $id = null ): mixed =>
				'agmcp_system_tool_denied' === $hook && 'sv_wc_background_job_test' === $id ? true : $value
		);

		$this->assertFalse( ( new SystemToolPolicy() )->verdict( [ 'id' => 'sv_wc_background_job_test' ] )->allowed );
	}

	/** No id yet means nothing to judge; the schema requires one before dispatch. */
	public function test_an_absent_id_is_not_judged(): void {
		$this->assertTrue( ( new SystemToolPolicy() )->verdict( [] )->allowed );
		$this->assertTrue( ( new SecretSettingPolicy() )->verdict( [] )->allowed );
	}
}
