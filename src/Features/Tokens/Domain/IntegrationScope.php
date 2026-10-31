<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * "integration:<slug>:<axis>". Parsed without asking which plugins are active,
 * so a plugin being off only leaves the grant with no tools to reach.
 */
final readonly class IntegrationScope implements ScopeInterface {

	public string $value;

	public function __construct( private IntegrationGroup $group, private bool $is_write ) {
		$this->value = $group->value . ( $is_write ? ':write' : ':read' );
	}

	public static function try_from_string( string $value ): ?self {
		$axis = strrchr( $value, ':' );

		if ( ':read' !== $axis && ':write' !== $axis ) {
			return null;
		}

		$group = IntegrationGroup::try_from_string( substr( $value, 0, -strlen( $axis ) ) );

		return null === $group ? null : new self( $group, ':write' === $axis );
	}

	public function key(): string {
		return $this->value;
	}

	public function is_write(): bool {
		return $this->is_write;
	}

	public function group(): IntegrationGroup {
		return $this->group;
	}

	public function label(): string {
		return ApiScope::derived_label( $this->group, $this->is_write );
	}

	public function description(): string {
		return $this->is_write ? $this->group->copy()->write_description() : $this->group->copy()->read_description();
	}
}
