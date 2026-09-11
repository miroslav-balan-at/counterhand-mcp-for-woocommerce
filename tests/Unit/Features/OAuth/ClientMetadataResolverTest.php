<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\OAuth;

use Counterhand\Features\OAuth\ClientMetadataResolver;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The client_id URL comes from the connecting app, so the fetch has to go
 * through core's safe HTTP function — the one that refuses private hosts.
 */
final class ClientMetadataResolverTest extends TestCase {

	private const CLIENT_ID = 'https://assistant.example/.well-known/client.json';

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
	}

	public function test_fetches_through_the_safe_http_function(): void {
		Functions\expect( 'wp_remote_get' )->never();
		Functions\expect( 'wp_safe_remote_get' )
			->once()
			->with( self::CLIENT_ID, \Mockery::type( 'array' ) )
			->andReturn( [ 'body' => $this->document() ] );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $this->document() );

		$metadata = ( new ClientMetadataResolver() )->resolve( self::CLIENT_ID );

		self::assertNotNull( $metadata );
		self::assertSame( 'Assistant', $metadata->client_name );
		self::assertSame( [ 'https://assistant.example/callback' ], $metadata->redirect_uris );
	}

	public function test_the_fetch_is_bounded_in_size(): void {
		Functions\expect( 'wp_safe_remote_get' )
			->once()
			->with(
				self::CLIENT_ID,
				\Mockery::on( static fn ( array $args ): bool => ( $args['limit_response_size'] ?? 0 ) > 0 && 0 === $args['redirection'] )
			)
			->andReturn( [ 'body' => $this->document() ] );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $this->document() );

		self::assertNotNull( ( new ClientMetadataResolver() )->resolve( self::CLIENT_ID ) );
	}

	public function test_a_document_claiming_another_client_id_is_refused(): void {
		Functions\when( 'wp_safe_remote_get' )->justReturn( [] );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $this->document( 'https://elsewhere.example/client.json' ) );

		self::assertNull( ( new ClientMetadataResolver() )->resolve( self::CLIENT_ID ) );
	}

	public function test_a_client_id_without_a_path_is_never_fetched(): void {
		Functions\expect( 'wp_safe_remote_get' )->never();

		self::assertNull( ( new ClientMetadataResolver() )->resolve( 'https://assistant.example' ) );
	}

	public function test_refresh_capability_is_what_the_document_declares(): void {
		Functions\when( 'wp_safe_remote_get' )->justReturn( [] );

		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $this->document( grant_types: [ 'authorization_code', 'refresh_token' ] ) );
		self::assertTrue( ( new ClientMetadataResolver() )->resolve( self::CLIENT_ID )->issues_refresh_tokens() );

		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $this->document() );
		self::assertFalse( ( new ClientMetadataResolver() )->resolve( self::CLIENT_ID )->issues_refresh_tokens() );
	}

	public function test_plain_http_client_ids_are_never_fetched(): void {
		Functions\expect( 'wp_safe_remote_get' )->never();

		self::assertNull( ( new ClientMetadataResolver() )->resolve( 'http://assistant.example/client.json' ) );
	}

	/** @param list<string> $grant_types */
	private function document( string $client_id = self::CLIENT_ID, array $grant_types = [] ): string {
		$document = [
			'client_id'     => $client_id,
			'client_name'   => 'Assistant',
			'redirect_uris' => [ 'https://assistant.example/callback', 'ftp://assistant.example/no' ],
		];

		if ( [] !== $grant_types ) {
			$document['grant_types'] = $grant_types;
		}

		return (string) json_encode( $document );
	}
}
