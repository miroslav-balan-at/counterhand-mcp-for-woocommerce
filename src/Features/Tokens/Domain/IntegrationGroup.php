<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * Another plugin's tools, as one group with its own scopes and toggles.
 *
 * Keyed by the slug alone, so a token row can be read back with the plugin
 * deactivated, and a grant for one plugin never reaches another.
 */
final readonly class IntegrationGroup implements ScopeGroupInterface {

	public const VALUE_PREFIX = 'integration:';

	private const OPTION_PREFIX = 'integration_';

	private const SLUG_PATTERN = '[a-z0-9][a-z0-9-]*';

	public string $value;

	private function __construct(
		public string $slug,
		private bool $writable,
		private IntegrationCopyInterface $copy,
	) {
		$this->value = self::VALUE_PREFIX . $slug;
	}

	/**
	 * For a slug written in code, where a bad one is a programmer's error.
	 *
	 * @throws \InvalidArgumentException When the slug is malformed.
	 */
	public static function of( string $slug, bool $writable = true, ?IntegrationCopyInterface $copy = null ): self {
		return self::try_of( $slug, $writable, $copy ) ?? throw new \InvalidArgumentException( sprintf( '"%s" is not an integration slug.', esc_html( $slug ) ) );
	}

	public static function try_of( string $slug, bool $writable = true, ?IntegrationCopyInterface $copy = null ): ?self {
		return 1 === preg_match( '/^' . self::SLUG_PATTERN . '$/', $slug )
			? new self( $slug, $writable, $copy ?? new GenericIntegrationCopy( $slug ) )
			: null;
	}

	/** Null for anything that is not "integration:<slug>". */
	public static function try_from_string( string $value ): ?self {
		return str_starts_with( $value, self::VALUE_PREFIX )
			? self::try_of( substr( $value, strlen( self::VALUE_PREFIX ) ) )
			: null;
	}

	public static function is_option_key( string $key ): bool {
		return 1 === preg_match( '/^' . self::OPTION_PREFIX . self::SLUG_PATTERN . '_(read|write)$/', $key );
	}

	public function key(): string {
		return $this->value;
	}

	public function label(): string {
		return $this->copy->name();
	}

	public function description(): string {
		return $this->copy->description();
	}

	public function copy(): IntegrationCopyInterface {
		return $this->copy;
	}

	public function noun(): string {
		return $this->label();
	}

	public function section(): ToolSection {
		return ToolSection::Integrations;
	}

	public function read_scope(): IntegrationScope {
		return new IntegrationScope( $this, false );
	}

	public function write_scope(): ?IntegrationScope {
		return $this->writable ? new IntegrationScope( $this, true ) : null;
	}

	public function has_write(): bool {
		return $this->writable;
	}

	public function read_option_key(): string {
		return self::OPTION_PREFIX . $this->slug . '_read';
	}

	public function write_option_key(): string {
		return self::OPTION_PREFIX . $this->slug . '_write';
	}

	public function enabled_by_default(): bool {
		return false;
	}

	/** Third-party code running as the token's owner: always worth a record. */
	public function is_always_logged(): bool {
		return true;
	}

	public function in_chat_by_default(): bool {
		return false;
	}
}
