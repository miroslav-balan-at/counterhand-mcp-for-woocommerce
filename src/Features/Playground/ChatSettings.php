<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Chat provider configuration.
 *
 * Stored in its own option (not the main settings array) so the API key is
 * never echoed back into a settings form or returned by a settings read —
 * only a masked hint is ever rendered.
 */
final class ChatSettings {

	private const OPTION = 'counterhand_chat';

	/**
	 * Empty provider and model on purpose: which one to offer first depends on
	 * what the site can actually serve, so ProviderRegistry::default_id()
	 * decides rather than a constant that would go stale.
	 */
	private const DEFAULTS = [
		'provider'    => '',
		'model'       => '',
		'base_url'    => '',
		'api_key'     => '',
		// null means "never chosen", which is not the same as "chosen to be
		// none" — an admin who unticks everything gets an empty array and keeps
		// it. See groups().
		'tool_groups' => null,
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

	/**
	 * Which tool groups this chat may reach.
	 *
	 * Every group enabled here costs a schema in the request on every single
	 * message, so this is narrower than what the store exposes to external
	 * clients and is meant to stay that way.
	 *
	 * @return list<ToolGroup>
	 */
	public function groups(): array {
		$stored = $this->get( 'tool_groups' );

		if ( ! is_array( $stored ) ) {
			return array_values( array_filter( ToolGroup::cases(), static fn ( ToolGroup $group ): bool => $group->in_chat_by_default() ) );
		}

		// tryFrom, so a group removed in a later release drops out of a stored
		// selection instead of fataling on every chat render.
		return array_values(
			array_filter(
				array_map(
					static fn ( $value ): ?ToolGroup => is_string( $value ) ? ToolGroup::tryFrom( $value ) : null,
					$stored
				)
			)
		);
	}

	/**
	 * @param list<string> $values Raw group slugs, as posted.
	 */
	public function save_groups( array $values ): void {
		$stored                = $this->all();
		$stored['tool_groups'] = array_values(
			array_map(
				static fn ( ToolGroup $group ): string => $group->value,
				array_filter( array_map( static fn ( string $value ): ?ToolGroup => ToolGroup::tryFrom( $value ), $values ) )
			)
		);

		update_option( self::OPTION, $stored, false );
		$this->cached = null;
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
