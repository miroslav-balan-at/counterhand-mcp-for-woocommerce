<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * Every group this store has right now: the shipped enum plus the integrations
 * discovered at runtime, which arrive through a filter so a shipped integration
 * uses the same path a third party would.
 */
final class ScopeGroups {

	public const INTEGRATIONS_FILTER = 'counterhand_integration_groups';

	/** Names of every plugin an integration exists for, active or not. */
	public const SUPPORTED_PLUGINS_FILTER = 'counterhand_supported_plugins';

	/** @return list<ScopeGroupInterface> */
	public static function all(): array {
		return [ ...ToolGroup::cases(), ...self::integrations() ];
	}

	public static function try_from( string $value ): ?ScopeGroupInterface {
		$shipped = ToolGroup::tryFrom( $value );
		if ( null !== $shipped ) {
			return $shipped;
		}

		$parsed = IntegrationGroup::try_from_string( $value );
		if ( null === $parsed ) {
			return null;
		}

		// The live group knows the plugin's real name and wording; a parsed one only its slug.
		foreach ( self::integrations() as $group ) {
			if ( $group->key() === $value ) {
				return $group;
			}
		}

		return $parsed;
	}

	/** False for an integration whose plugin is off: it has nothing to offer right now. */
	public static function is_live( ScopeGroupInterface $group ): bool {
		if ( ToolSection::Integrations !== $group->section() ) {
			return true;
		}

		foreach ( self::integrations() as $live ) {
			if ( $live->key() === $group->key() ) {
				return true;
			}
		}

		return false;
	}

	/** @return list<ScopeGroupInterface> */
	public static function integrations(): array {
		$groups = apply_filters( self::INTEGRATIONS_FILTER, [] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- the constant is counterhand_ prefixed.

		if ( ! is_array( $groups ) ) {
			return [];
		}

		// One row per plugin however many sources name it. The first keeps it, with its
		// wording, unless it is read-only and a later source offers writes.
		$unique = [];

		foreach ( $groups as $group ) {
			if ( ! $group instanceof ScopeGroupInterface ) {
				continue;
			}

			if ( ! isset( $unique[ $group->key() ] ) || ( $group->has_write() && ! $unique[ $group->key() ]->has_write() ) ) {
				$unique[ $group->key() ] = $group;
			}
		}

		ksort( $unique );

		return array_values( $unique );
	}

	/**
	 * Supported plugins that offer nothing here right now, so a store owner learns
	 * what switching one on would add. Only names this code ships, never the store's.
	 *
	 * @return list<string>
	 */
	public static function supported_but_inactive(): array {
		$names = apply_filters( self::SUPPORTED_PLUGINS_FILTER, [] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- the constant is counterhand_ prefixed.

		if ( ! is_array( $names ) ) {
			return [];
		}

		$active   = array_map( static fn ( ScopeGroupInterface $group ): string => $group->label(), self::integrations() );
		$inactive = array_diff( array_unique( array_filter( $names, 'is_string' ) ), $active );
		natcasesort( $inactive );

		return array_values( $inactive );
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
