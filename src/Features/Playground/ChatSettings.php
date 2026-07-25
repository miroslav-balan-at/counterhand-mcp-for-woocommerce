<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Playground;

defined( 'ABSPATH' ) || exit;

/**
 * Chat provider configuration.
 *
 * Stored in its own option (not the main settings array) so the API key is
 * never echoed back into a settings form or returned by a settings read —
 * only a masked hint is ever rendered.
 */
final class ChatSettings {

	private const OPTION = 'agmcp_chat';

	private const DEFAULTS = [
		'provider' => 'anthropic',
		'model'    => 'claude-opus-5',
		'base_url' => '',
		'api_key'  => '',
	];

	private ?array $cached = null;

	public function provider_id(): string {
		return (string) $this->get( 'provider' );
	}

	public function model(): string {
		return (string) $this->get( 'model' );
	}

	public function base_url(): string {
		return (string) $this->get( 'base_url' );
	}

	public function api_key(): string {
		return (string) $this->get( 'api_key' );
	}

	public function is_configured(): bool {
		return '' !== $this->model();
	}

	/** Enough of the key to recognise it, never enough to use it. */
	public function masked_key(): string {
		$key = $this->api_key();

		if ( '' === $key ) {
			return '';
		}

		return strlen( $key ) < 12
			? str_repeat( '•', strlen( $key ) )
			: substr( $key, 0, 6 ) . '…' . substr( $key, -4 );
	}

	public function save( string $provider, string $model, string $base_url, ?string $api_key ): void {
		$stored = [
			'provider' => sanitize_key( $provider ),
			'model'    => sanitize_text_field( $model ),
			'base_url' => '' === $base_url ? '' : esc_url_raw( $base_url ),
			// A blank submission keeps the stored key rather than wiping it.
			'api_key'  => null === $api_key || '' === $api_key ? $this->api_key() : sanitize_text_field( $api_key ),
		];

		update_option( self::OPTION, $stored, false );
		$this->cached = null;
	}

	public function forget_key(): void {
		$stored            = $this->all();
		$stored['api_key'] = '';

		update_option( self::OPTION, $stored, false );
		$this->cached = null;
	}

	public function all(): array {
		if ( null === $this->cached ) {
			$stored       = get_option( self::OPTION, [] );
			$this->cached = array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : [] );
		}

		return $this->cached;
	}

	private function get( string $key ): mixed {
		return $this->all()[ $key ] ?? null;
	}
}
