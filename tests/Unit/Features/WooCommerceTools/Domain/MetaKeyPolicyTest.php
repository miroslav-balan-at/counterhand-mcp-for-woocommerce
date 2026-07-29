<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\WooCommerceTools\Domain;

use AgentGateMcp\Features\WooCommerceTools\Domain\MetaKeyPolicy;
use AgentGateMcp\Features\WooCommerceTools\Domain\MetaOwner;
use AgentGateMcp\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The one place this plugin judges something WooCommerce will not judge for it.
 *
 * WooCommerce authorises the object — "may this user edit customers?" — and then
 * writes whatever meta key it is handed. A customer is a WordPress user, so the
 * keys on offer include the ones holding roles, capabilities and login sessions.
 * These tests are the record that each of those is refused.
 *
 * The store this was developed against prefixes its tables with hms_, which is
 * why the prefix is a constructor argument and why the tests below use a
 * non-default one: a policy that only knew wp_capabilities would have passed
 * every test here and protected nothing in production.
 */
final class MetaKeyPolicyTest extends TestCase {

	private const PREFIX = 'hms_';

	protected function setUp(): void {
		parent::setUp();

		// The stubs' is_protected_meta() calls this; the real one is filterable
		// and returning the unfiltered value is what an unmodified store does.
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'wp_json_encode' )->alias( static fn ( $v ): string => (string) json_encode( $v ) );
	}

	private function policy(): MetaKeyPolicy {
		return new MetaKeyPolicy( self::PREFIX );
	}

	/**
	 * The headline case. A write here makes the customer an administrator, and
	 * it is reachable through an endpoint whose only other gate is "may you edit
	 * customers at all".
	 *
	 * @dataProvider escalation_keys
	 */
	public function test_a_key_that_grants_privileges_is_never_writable( string $key ): void {
		$verdict = $this->policy()->may_write( $key, [ 'administrator' => true ], MetaOwner::User );

		$this->assertFalse( $verdict->allowed, $key . ' is writable.' );
		$this->assertNotSame( '', $verdict->reason );
	}

	/** @dataProvider escalation_keys */
	public function test_a_key_that_grants_privileges_is_never_readable( string $key ): void {
		$this->assertFalse( $this->policy()->may_read( $key, MetaOwner::User )->allowed, $key . ' is readable.' );
	}

	/**
	 * Reserved keys are refused on products too. The capabilities key means
	 * nothing in postmeta, but a rule that only applied to users would be one
	 * mistaken MetaOwner away from not applying at all.
	 *
	 * @dataProvider escalation_keys
	 */
	public function test_reserved_keys_are_refused_whatever_the_owner( string $key ): void {
		$this->assertFalse( $this->policy()->may_write( $key, 'x', MetaOwner::Post )->allowed, $key );
	}

	/** @return iterable<string, array{string}> */
	public static function escalation_keys(): iterable {
		yield 'this install\'s capabilities' => [ self::PREFIX . 'capabilities' ];
		yield 'this install\'s user level'   => [ self::PREFIX . 'user_level' ];
		yield 'default prefix capabilities'  => [ 'wp_capabilities' ];
		yield 'network site capabilities'    => [ 'wp_2_capabilities' ];
		yield 'login sessions'               => [ 'session_tokens' ];
		yield 'password reset nonce'         => [ 'default_password_nonce' ];
		yield 'account activation key'       => [ 'user_activation_key' ];
	}

	/**
	 * Verified on WooCommerce 10.9.4: this write reaches usermeta through the
	 * customers endpoint and succeeds. It is the reason the reserved list is not
	 * theoretical, and the case most worth never regressing.
	 */
	public function test_the_session_token_write_that_woocommerce_would_allow_is_refused(): void {
		$this->assertFalse(
			$this->policy()->may_write( 'session_tokens', [ 'forged' => [ 'expiration' => 99999999999 ] ], MetaOwner::User )->allowed
		);
	}

	/** Case is not a way round the list. */
	public function test_reserved_keys_are_matched_regardless_of_case(): void {
		$this->assertFalse( $this->policy()->may_write( 'Session_Tokens', 'x', MetaOwner::User )->allowed );
		$this->assertFalse( $this->policy()->may_write( 'HMS_Capabilities', 'x', MetaOwner::User )->allowed );
	}

	public function test_an_ordinary_custom_field_is_allowed(): void {
		$this->assertTrue( $this->policy()->may_write( 'supplier_reference', 'ACME-9', MetaOwner::Post )->allowed );
		$this->assertTrue( $this->policy()->may_read( 'supplier_reference', MetaOwner::Post )->allowed );
	}

	/**
	 * WordPress marks underscore-prefixed meta private, and WooCommerce keeps
	 * its own bookkeeping there — _price, _stock. Writing one behind the API
	 * leaves a product inconsistent with what WooCommerce reports about it.
	 */
	public function test_a_private_key_is_refused_in_both_directions(): void {
		$this->assertFalse( $this->policy()->may_write( '_price', '1.00', MetaOwner::Post )->allowed );
		$this->assertFalse( $this->policy()->may_read( '_price', MetaOwner::Post )->allowed );
	}

	/**
	 * The underscore rule is core's is_protected_meta(), not a str_starts_with()
	 * of ours, so a store that deliberately exposes one of its keys is honoured.
	 * That is the difference between reusing WordPress and reimplementing it.
	 */
	public function test_a_store_that_unprotects_a_key_is_honoured(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value, mixed $key = null ): mixed =>
				'is_protected_meta' === $hook && '_shared_with_partners' === $key ? false : $value
		);

		$this->assertTrue( $this->policy()->may_read( '_shared_with_partners', MetaOwner::Post )->allowed );
	}

	/**
	 * Storing serialized data is the setup for object injection: some other
	 * plugin unserializes meta it trusts. Detected with core's own is_serialized()
	 * so the policy and WordPress cannot disagree about what serialized means.
	 */
	public function test_a_serialized_payload_is_refused(): void {
		$this->assertFalse( $this->policy()->may_write( 'note', 'O:8:"stdClass":0:{}', MetaOwner::Post )->allowed );
		$this->assertFalse( $this->policy()->may_write( 'note', 'a:1:{i:0;s:3:"bad";}', MetaOwner::Post )->allowed );
	}

	/** A string that merely mentions a colon is not serialized data. */
	public function test_ordinary_text_is_not_mistaken_for_serialized_data(): void {
		$this->assertTrue( $this->policy()->may_write( 'note', 'a:1 ratio, not serialized', MetaOwner::Post )->allowed );
	}

	public function test_a_key_that_is_not_a_usable_field_name_is_refused(): void {
		$this->assertFalse( $this->policy()->may_write( '9lives', 'x', MetaOwner::Post )->allowed );
		$this->assertFalse( $this->policy()->may_write( 'has spaces', 'x', MetaOwner::Post )->allowed );
		$this->assertFalse( $this->policy()->may_write( '', 'x', MetaOwner::Post )->allowed );
		$this->assertFalse( $this->policy()->may_write( str_repeat( 'k', 256 ), 'x', MetaOwner::Post )->allowed );
	}

	public function test_an_oversized_value_is_refused(): void {
		$this->assertFalse( $this->policy()->may_write( 'blob', str_repeat( 'x', 65537 ), MetaOwner::Post )->allowed );
		$this->assertTrue( $this->policy()->may_write( 'blob', str_repeat( 'x', 65536 ), MetaOwner::Post )->allowed );
	}

	public function test_a_list_value_is_allowed_and_an_object_is_not(): void {
		$this->assertTrue( $this->policy()->may_write( 'sizes', [ 'S', 'M' ], MetaOwner::Post )->allowed );
		$this->assertFalse( $this->policy()->may_write( 'thing', new \stdClass(), MetaOwner::Post )->allowed );
	}

	/** Deleting is a write of null, and must still clear the reserved gate. */
	public function test_deleting_a_reserved_key_is_refused_too(): void {
		$this->assertFalse( $this->policy()->may_write( 'session_tokens', null, MetaOwner::User )->allowed );
		$this->assertTrue( $this->policy()->may_write( 'supplier_reference', null, MetaOwner::Post )->allowed );
	}

	/**
	 * A denial an agent cannot act on gets retried with a variation. Each reason
	 * has to name the key and say the rule is not negotiable.
	 */
	public function test_a_denial_explains_itself_to_the_agent(): void {
		$reason = $this->policy()->may_write( self::PREFIX . 'capabilities', 'x', MetaOwner::User )->reason;

		$this->assertStringContainsString( self::PREFIX . 'capabilities', $reason );
		$this->assertStringContainsString( 'never permitted', $reason );
	}
}
