<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\OAuth;

defined( 'ABSPATH' ) || exit;

/**
 * Single-use, short-lived authorization codes. Transient-backed: consuming
 * a code deletes it atomically enough for the 2-minute window it lives in.
 */
final readonly class AuthorizationCodeStore {

	private const TTL_SECONDS = 120;

	/** @param list<string> $scopes */
	// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.resourceFound -- "resource" is the RFC 8707 term for the OAuth resource indicator.
	public function mint( string $client_id, string $redirect_uri, string $code_challenge, array $scopes, int $user_id, string $resource ): string {
		$code = 'agc_' . rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- encoding random bytes, not obfuscation.

		set_transient(
			'agmcp_ac_' . hash( 'sha256', $code ),
			[
				'client_id'      => $client_id,
				'redirect_uri'   => $redirect_uri,
				'code_challenge' => $code_challenge,
				'scopes'         => $scopes,
				'user_id'        => $user_id,
				'resource'       => $resource,
			],
			self::TTL_SECONDS
		);

		return $code;
	}

	/** @return array{client_id: string, redirect_uri: string, code_challenge: string, scopes: list<string>, user_id: int, resource: string}|null */
	public function consume( string $code ): ?array {
		$transient_key = 'agmcp_ac_' . hash( 'sha256', $code );

		$grant = get_transient( $transient_key );
		delete_transient( $transient_key );

		return is_array( $grant ) ? $grant : null;
	}
}
