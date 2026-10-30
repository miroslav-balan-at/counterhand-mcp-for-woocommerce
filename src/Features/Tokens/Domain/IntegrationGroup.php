<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * Another plugin's tools, as one group with its own scopes and toggles.
 *
 * Everything here derives from the slug, so a token row can be read back with
 * the plugin deactivated, and a grant for one plugin never reaches another.
 */
final readonly class IntegrationGroup implements ScopeGroupInterface {

	public const VALUE_PREFIX = 'integration:';

	private const OPTION_PREFIX = 'integration_';

	private const SLUG_PATTERN = '[a-z0-9][a-z0-9-]*';

	/** A namespace's own spelling, where capitalizing its words would misname it. */
	private const NAMES = [
		'rank-math' => 'Rank Math',
		'wpforms'   => 'WPForms',
		'yoast-seo' => 'Yoast SEO',
	];

	public string $value;

	private function __construct( public string $slug, private bool $writable ) {
		$this->value = self::VALUE_PREFIX . $slug;
	}

	public static function try_of( string $slug, bool $writable = true ): ?self {
		return 1 === preg_match( '/^' . self::SLUG_PATTERN . '$/', $slug ) ? new self( $slug, $writable ) : null;
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
		return self::NAMES[ $this->slug ] ?? ucwords( str_replace( '-', ' ', $this->slug ) );
	}

	public function description(): string {
		return sprintf(
			/* translators: %s: name of another plugin, e.g. "Yoast SEO". */
			__( 'What %s offers to AI clients. It still checks its own permissions on every call.', 'counterhand-mcp-for-woocommerce' ),
			$this->label()
		);
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
