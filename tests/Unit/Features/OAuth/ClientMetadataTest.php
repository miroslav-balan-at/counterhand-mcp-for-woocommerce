<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\OAuth;

use AgentGateMcp\Features\OAuth\ClientMetadata;
use AgentGateMcp\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class ClientMetadataTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( static fn ( string $url ) => parse_url( $url ) );
	}

	public function test_exact_redirect_uri_is_allowed(): void {
		$client = new ClientMetadata(
			'https://claude.ai/mcp',
			'Claude',
			[ 'https://claude.ai/api/mcp/auth_callback' ]
		);

		self::assertTrue( $client->allows_redirect_uri( 'https://claude.ai/api/mcp/auth_callback' ) );
	}

	public function test_unregistered_redirect_uri_is_rejected(): void {
		$client = new ClientMetadata(
			'https://claude.ai/mcp',
			'Claude',
			[ 'https://claude.ai/api/mcp/auth_callback' ]
		);

		self::assertFalse( $client->allows_redirect_uri( 'https://evil.example/steal' ) );
	}

	public function test_loopback_matches_port_insensitively(): void {
		$client = new ClientMetadata(
			'https://tool.example/mcp',
			'Local Tool',
			[ 'http://127.0.0.1:8080/callback' ]
		);

		// RFC 8252 §7.3: loopback redirects match regardless of port.
		self::assertTrue( $client->allows_redirect_uri( 'http://127.0.0.1:54321/callback' ) );
	}

	public function test_loopback_still_requires_matching_path(): void {
		$client = new ClientMetadata(
			'https://tool.example/mcp',
			'Local Tool',
			[ 'http://127.0.0.1:8080/callback' ]
		);

		self::assertFalse( $client->allows_redirect_uri( 'http://127.0.0.1:9999/different-path' ) );
	}
}
