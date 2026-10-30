<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Every group this store has right now: the shipped enum plus the integrations
 * discovered at runtime, which arrive through a filter so a shipped integration
 * uses the same path a third party would.
 */
final class ScopeGroups {

	public const INTEGRATIONS_FILTER = 'counterhand_integration_groups';

	/** @return list<ScopeGroupInterface> */
	public static function all(): array {
		return [ ...ToolGroup::cases(), ...self::integrations() ];
	}

	public static function try_from( string $value ): ?ScopeGroupInterface {
		return ToolGroup::tryFrom( $value ) ?? IntegrationGroup::try_from_string( $value );
	}

	/** @return list<ScopeGroupInterface> */
	public static function integrations(): array {
		$groups = apply_filters( self::INTEGRATIONS_FILTER, [] );

		return is_array( $groups )
			? array_values( array_filter( $groups, static fn ( mixed $group ): bool => $group instanceof ScopeGroupInterface ) )
			: [];
	}

	/** @return list<ScopeInterface> */
	public static function integration_scopes(): array {
		$scopes = [];

		foreach ( self::integrations() as $group ) {
			$scopes[] = $group->read_scope();

			$write = $group->write_scope();
			if ( null !== $write ) {
				$scopes[] = $write;
			}
		}

		return $scopes;
	}
}
