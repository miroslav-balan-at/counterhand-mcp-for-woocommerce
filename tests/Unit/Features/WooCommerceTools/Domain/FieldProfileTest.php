<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Tests\Unit\TestCase;

/**
 * The only place a descriptor is allowed to restate anything about WooCommerce
 * — and then only field names. Everything here has to work on names alone, or
 * the profile has become a second copy of the schema.
 */
final class FieldProfileTest extends TestCase {

	private const COUPON_ARGS = [
		'id'            => [ 'type' => 'integer' ],
		'code'          => [ 'type' => 'string' ],
		'amount'        => [ 'type' => 'string' ],
		'discount_type' => [ 'type' => 'string' ],
	];

	public function test_everything_prunes_neither_side(): void {
		$profile = FieldProfile::everything();

		$this->assertFalse( $profile->prunes_input() );
		$this->assertSame( self::COUPON_ARGS, $profile->select( self::COUPON_ARGS ) );
		$this->assertNull( $profile->output_fields() );
	}

	public function test_everything_leaves_writes_open(): void {
		$this->assertTrue( FieldProfile::everything()->allow_additional );
	}

	public function test_select_keeps_only_the_named_arguments(): void {
		$profile = new FieldProfile( [ 'code', 'amount' ], [] );

		$this->assertSame(
			[
				'code'   => [ 'type' => 'string' ],
				'amount' => [ 'type' => 'string' ],
			],
			$profile->select( self::COUPON_ARGS )
		);
	}

	/**
	 * Ordering by WooCommerce rather than by the profile means editing a
	 * descriptor cannot reshuffle a published schema, and id-first stays first.
	 */
	public function test_select_keeps_woocommerce_ordering_not_the_profiles(): void {
		$profile = new FieldProfile( [ 'discount_type', 'id' ], [] );

		$this->assertSame( [ 'id', 'discount_type' ], array_keys( $profile->select( self::COUPON_ARGS ) ) );
	}

	/** The ordinary cost of a WooCommerce upgrade: one field gone, the rest fine. */
	public function test_a_name_the_route_no_longer_declares_is_simply_ignored(): void {
		$profile = new FieldProfile( [ 'code', 'expiry_date' ], [] );

		$this->assertSame( [ 'code' ], array_keys( $profile->select( self::COUPON_ARGS ) ) );
		$this->assertFalse( $profile->is_stale_against( self::COUPON_ARGS ) );
	}

	public function test_a_profile_matching_nothing_is_stale(): void {
		$profile = new FieldProfile( [ 'renamed_away', 'also_gone' ], [] );

		$this->assertTrue( $profile->is_stale_against( self::COUPON_ARGS ) );
	}

	/**
	 * A route can legitimately declare no arguments at all — /system_status is
	 * one. That is not the profile's fault and must not trigger the fallback.
	 */
	public function test_a_route_declaring_no_arguments_is_not_evidence_of_staleness(): void {
		$this->assertFalse( ( new FieldProfile( [ 'code' ], [] ) )->is_stale_against( [] ) );
	}

	public function test_everything_is_never_stale(): void {
		$this->assertFalse( FieldProfile::everything()->is_stale_against( self::COUPON_ARGS ) );
	}

	public function test_output_fields_are_joined_for_the_rest_fields_param(): void {
		$profile = new FieldProfile( [], [ 'id', 'code', 'amount' ] );

		$this->assertSame( 'id,code,amount', $profile->output_fields() );
	}

	/** Null rather than an empty string, so callers can tell "everything" from "nothing". */
	public function test_no_output_fields_means_ask_for_everything(): void {
		$this->assertNull( ( new FieldProfile( [ 'code' ], [] ) )->output_fields() );
	}
}
