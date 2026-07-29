<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\Settings;

use AgentGateMcp\Features\Settings\ClientGroup;
use AgentGateMcp\Features\Settings\ConnectionMatcher;
use AgentGateMcp\Features\Settings\McpClient;
use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Features\Tokens\Domain\ApiToken;
use AgentGateMcp\Features\Tokens\Domain\GrantedScopeSet;
use AgentGateMcp\Features\Tokens\Domain\TokenId;
use AgentGateMcp\Features\Tokens\Domain\TokenRepositoryInterface;
use AgentGateMcp\Features\Tokens\Domain\TokenStatus;
use AgentGateMcp\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class ConnectionMatcherTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_parse_url' )->alias(
			static fn ( string $url, int $component = -1 ) => parse_url( $url, $component )
		);
	}

	/** @param list<ApiToken> $tokens */
	private function matcher( array $tokens ): ConnectionMatcher {
		$repository = $this->createStub( TokenRepositoryInterface::class );
		$repository->method( 'list_all' )->willReturn( $tokens );

		return new ConnectionMatcher( $repository );
	}

	private function token(
		?string $client_id,
		TokenStatus $status = TokenStatus::Active,
		?\DateTimeImmutable $created_at = null,
	): ApiToken {
		return new ApiToken(
			id: 1,
			token_id: TokenId::try_from_string( 'testtoken0000000' ) ?? throw new \RuntimeException( 'bad id' ),
			label: 'test',
			scopes: GrantedScopeSet::from_values( ApiScope::values() ),
			status: $status,
			owner_user_id: 1,
			created_at: $created_at ?? new \DateTimeImmutable( '2026-07-25T10:00:00Z' ),
			last_used_at: null,
			expires_at: null,
			client_id: $client_id,
		);
	}

	private function claude_client(): McpClient {
		return new McpClient(
			id: 'claude',
			name: 'Claude',
			blurb: '',
			group: ClientGroup::Cloud,
			steps: [],
			match_hosts: [ 'claude.ai', 'anthropic.com' ],
		);
	}

	public function test_matches_a_subdomain_of_a_declared_host(): void {
		$matcher = $this->matcher( [ $this->token( 'https://connectors.anthropic.com/client.json' ) ] );

		self::assertSame(
			[ 'claude' => 'connectors.anthropic.com' ],
			$matcher->connected( [ $this->claude_client() ] )
		);
	}

	public function test_a_lookalike_host_does_not_match(): void {
		$matcher = $this->matcher( [ $this->token( 'https://notanthropic.com/client.json' ) ] );

		self::assertSame( [], $matcher->connected( [ $this->claude_client() ] ) );
	}

	public function test_revoked_tokens_never_count(): void {
		$matcher = $this->matcher( [ $this->token( 'https://claude.ai/client.json', TokenStatus::Revoked ) ] );

		self::assertSame( [], $matcher->connected( [ $this->claude_client() ] ) );
	}

	public function test_newest_since_ignores_older_connections(): void {
		$old = $this->token( 'https://claude.ai/a.json', created_at: new \DateTimeImmutable( '2026-07-25T09:00:00Z' ) );
		$new = $this->token( 'https://claude.ai/b.json', created_at: new \DateTimeImmutable( '2026-07-25T11:00:00Z' ) );

		$matcher = $this->matcher( [ $old, $new ] );
		$since   = ( new \DateTimeImmutable( '2026-07-25T10:30:00Z' ) )->getTimestamp();

		self::assertSame( $new, $matcher->newest_since( $since ) );
		self::assertNull( $this->matcher( [ $old ] )->newest_since( $since ) );
	}
}
