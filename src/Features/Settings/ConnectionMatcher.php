<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Settings;

use AgentGateMcp\Features\Tokens\Domain\ApiToken;
use AgentGateMcp\Features\Tokens\Domain\TokenRepositoryInterface;
use AgentGateMcp\Features\Tokens\Domain\TokenStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Marks a client card as connected by matching live tokens back to it.
 *
 * Best effort by construction, and the UI says so. A token records the client's
 * CIMD document URL, which identifies the vendor but not the product: Claude on
 * the web, Claude Desktop and Claude Code all authorise from Anthropic hosts
 * and cannot be told apart from that URL alone. The Connections tab stays the
 * authoritative list; this only answers "has anything from this vendor
 * connected?", which is what the card needs in order to stop nagging.
 */
final readonly class ConnectionMatcher {

	public function __construct( private TokenRepositoryInterface $repository ) {}

	/**
	 * Client ids that currently have at least one live connection.
	 *
	 * @param list<McpClient> $clients
	 * @return array<string, string> Client id => the host it connected from.
	 */
	public function connected( array $clients ): array {
		$now   = self::now();
		$hosts = [];

		foreach ( $this->repository->list_all() as $token ) {
			$host = $this->token_host( $token, $now );

			if ( null !== $host ) {
				$hosts[] = $host;
			}
		}

		if ( [] === $hosts ) {
			return [];
		}

		$matched = [];

		foreach ( $clients as $client ) {
			foreach ( $hosts as $host ) {
				if ( $this->host_matches( $host, $client->match_hosts ) ) {
					$matched[ $client->id ] = $host;
					break;
				}
			}
		}

		return $matched;
	}

	/** Newest live connection, used to detect one arriving while the tab is open. */
	public function newest_since( int $timestamp ): ?ApiToken {
		$now    = self::now();
		$newest = null;

		foreach ( $this->repository->list_all() as $token ) {
			if ( ! $this->is_live( $token, $now ) || $token->created_at->getTimestamp() < $timestamp ) {
				continue;
			}

			if ( null === $newest || $token->created_at > $newest->created_at ) {
				$newest = $token;
			}
		}

		return $newest;
	}

	private function token_host( ApiToken $token, \DateTimeImmutable $now ): ?string {
		if ( ! $this->is_live( $token, $now ) || null === $token->client_id ) {
			return null;
		}

		$host = wp_parse_url( $token->client_id, PHP_URL_HOST );

		return is_string( $host ) && '' !== $host ? strtolower( $host ) : null;
	}

	private function is_live( ApiToken $token, \DateTimeImmutable $now ): bool {
		return TokenStatus::Active === $token->status && ! $token->is_expired( $now );
	}

	private static function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Matches the host itself or any subdomain of it, so console.anthropic.com
	 * counts for anthropic.com without matching notanthropic.com.
	 *
	 * @param list<string> $candidates
	 */
	private function host_matches( string $host, array $candidates ): bool {
		foreach ( $candidates as $candidate ) {
			$candidate = strtolower( $candidate );

			if ( $host === $candidate || str_ends_with( $host, '.' . $candidate ) ) {
				return true;
			}
		}

		return false;
	}
}
