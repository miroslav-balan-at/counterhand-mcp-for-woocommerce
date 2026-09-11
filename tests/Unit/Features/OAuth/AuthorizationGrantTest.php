<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\OAuth;

use Counterhand\Features\OAuth\Domain\AuthorizationGrant;
use Counterhand\Tests\Unit\TestCase;

final class AuthorizationGrantTest extends TestCase {

	public function test_round_trips_through_the_transient_shape(): void {
		$grant = new AuthorizationGrant(
			client_id: 'https://client.example/metadata.json',
			redirect_uri: 'https://client.example/callback',
			code_challenge: str_repeat( 'c', 43 ),
			scopes: [ 'products:read', 'orders:read' ],
			user_id: 7,
			resource: 'https://store.example/wp-json/counterhand/v1/mcp',
		);

		$rehydrated = AuthorizationGrant::from_array( $grant->to_array() );

		self::assertNotNull( $rehydrated );
		self::assertSame( $grant->to_array(), $rehydrated->to_array() );
	}

	/** A code minted before refresh support, redeemed after the upgrade, must still work — as a non-refreshing grant. */
	public function test_a_payload_without_the_refresh_flag_hydrates_as_not_refreshing(): void {
		$grant = AuthorizationGrant::from_array(
			[
				'client_id'      => 'https://client.example/metadata.json',
				'redirect_uri'   => 'https://client.example/callback',
				'code_challenge' => str_repeat( 'c', 43 ),
				'scopes'         => [ 'products:read' ],
				'user_id'        => 7,
				'resource'       => 'https://store.example/mcp',
			]
		);

		self::assertNotNull( $grant );
		self::assertFalse( $grant->issues_refresh_token );
	}

	/** @dataProvider malformed_payloads */
	public function test_from_array_fails_closed_on_malformed_data( mixed $payload ): void {
		self::assertNull( AuthorizationGrant::from_array( $payload ) );
	}

	public static function malformed_payloads(): array {
		$valid = [
			'client_id'      => 'https://client.example/metadata.json',
			'redirect_uri'   => 'https://client.example/callback',
			'code_challenge' => str_repeat( 'c', 43 ),
			'scopes'         => [ 'products:read' ],
			'user_id'        => 7,
			'resource'       => 'https://store.example/wp-json/counterhand/v1/mcp',
		];

		return [
			'expired transient (false)' => [ false ],
			'not an array'              => [ 'agc_something' ],
			'empty array'               => [ [] ],
			'missing client_id'         => [ array_diff_key( $valid, [ 'client_id' => true ] ) ],
			'missing user_id'           => [ array_diff_key( $valid, [ 'user_id' => true ] ) ],
			'client_id not a string'    => [ array_merge( $valid, [ 'client_id' => 42 ] ) ],
			'user_id not an int'        => [ array_merge( $valid, [ 'user_id' => '7' ] ) ],
			'scopes not an array'       => [ array_merge( $valid, [ 'scopes' => 'products:read' ] ) ],
			'scope entry not a string'  => [ array_merge( $valid, [ 'scopes' => [ 'products:read', 5 ] ] ) ],
			'refresh flag not a bool'   => [ array_merge( $valid, [ 'issues_refresh_token' => 'yes' ] ) ],
		];
	}
}
