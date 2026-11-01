<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\McpServer;

use Brain\Monkey\Functions;
use Counterhand\Features\McpServer\ConfirmationState;
use Counterhand\Tests\Doubles\AgentFactory;
use Counterhand\Tests\Unit\TestCase;

final class ConfirmationStateTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_salt' )->justReturn( 'salt' );
	}

	public function test_a_state_verifies_only_for_the_exact_call_it_was_issued_for(): void {
		$state = new ConfirmationState();
		$agent = AgentFactory::with_scopes( [ 'products:write' ] );
		$token = $state->issue( $agent, 'update_product', [ 'id' => 5, 'regular_price' => '9' ] );

		$this->assertNotNull( $state->verified_nonce( $token, $agent, 'update_product', [ 'regular_price' => '9', 'id' => 5 ] ), 'order of keys does not matter' );
		$this->assertNull( $state->verified_nonce( $token, $agent, 'update_product', [ 'id' => 5, 'regular_price' => '1' ] ) );
		$this->assertNull( $state->verified_nonce( $token, $agent, 'delete_product', [ 'id' => 5, 'regular_price' => '9' ] ) );
		$this->assertNull( $state->verified_nonce( $token . 'x', $agent, 'update_product', [ 'id' => 5, 'regular_price' => '9' ] ) );
		$this->assertNull( $state->verified_nonce( 'garbage', $agent, 'update_product', [] ) );
	}

	public function test_a_forged_payload_with_no_valid_signature_is_refused(): void {
		$state   = new ConfirmationState();
		$agent   = AgentFactory::with_scopes( [ 'products:write' ] );
		$payload = base64_encode( (string) json_encode( [ 'token' => $agent->token->id, 'tool' => 'x', 'expires' => time() + 999 ] ) );

		$this->assertNull( $state->verified_nonce( $payload . '.00', $agent, 'x', [] ) );
	}

	public function test_an_expired_state_is_refused(): void {
		$state = new ConfirmationState();
		$agent = AgentFactory::with_scopes( [ 'products:write' ] );
		$token = $state->issue( $agent, 'a', [] );

		[ $encoded ] = explode( '.', $token );
		$claims      = json_decode( (string) base64_decode( $encoded ), true );
		$claims['expires'] = time() - 1;
		$payload     = (string) wp_json_encode( $claims );
		$forged      = base64_encode( $payload ) . '.' . hash_hmac( 'sha256', $payload, 'salt' );

		$this->assertNull( $state->verified_nonce( $forged, $agent, 'a', [] ) );
	}
}
