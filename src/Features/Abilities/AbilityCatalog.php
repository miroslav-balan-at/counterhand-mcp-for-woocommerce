<?php

declare( strict_types=1 );

namespace Counterhand\Features\Abilities;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Which abilities on this store may become tools, and the groups they fall into.
 *
 * Grouped by namespace rather than category: the namespace is the plugin, while
 * categories are the plugin's own filing (`wpforms-forms`) or shared (`site`).
 */
final class AbilityCatalog {

	/**
	 * woocommerce/*: all seven duplicate generated tools, and bridging them would
	 * let an integration grant reach products and orders past those groups' scopes.
	 * core/*: its environment report is what system:read gates, and it is no plugin.
	 * mcp-adapter/*: meta-tools that call any ability, which would bypass every gate.
	 */
	private const SKIPPED_NAMESPACES = [ 'woocommerce', 'core', 'mcp-adapter', 'counterhand' ];

	/** @var ?list<\WP_Ability> */
	private ?array $abilities = null;

	/** @return list<\WP_Ability> */
	public function abilities(): array {
		// Asking before init is an error in core, and the answer would be empty.
		if ( ! did_action( 'init' ) ) {
			return [];
		}

		return $this->abilities ??= array_values( array_filter( wp_get_abilities(), [ self::class, 'is_exposed' ] ) );
	}

	/**
	 * The group an ability belongs to. Writable only when the plugin actually
	 * offers a write, so the settings row does not show a switch that does nothing.
	 */
	public function group_of( \WP_Ability $ability ): ?IntegrationGroup {
		$namespace = self::namespace_of( $ability );

		foreach ( $this->abilities() as $candidate ) {
			if ( self::namespace_of( $candidate ) === $namespace && ! AbilityAnnotations::of( $candidate )->read_only ) {
				return IntegrationGroup::try_of( $namespace );
			}
		}

		return IntegrationGroup::try_of( $namespace, false );
	}

	/** @return list<IntegrationGroup> In name order, so the screens do not reshuffle. */
	public function groups(): array {
		$groups = [];

		foreach ( $this->abilities() as $ability ) {
			$groups[ self::namespace_of( $ability ) ] ??= $this->group_of( $ability );
		}

		ksort( $groups );

		return array_values( array_filter( $groups ) );
	}

	/** Plugins mostly set meta.mcp.public, core sets meta.public; either one counts. */
	private static function is_exposed( \WP_Ability $ability ): bool {
		$meta = $ability->get_meta();

		return ! in_array( self::namespace_of( $ability ), self::SKIPPED_NAMESPACES, true )
			&& ( true === ( $meta['public'] ?? null ) || true === ( $meta['mcp']['public'] ?? null ) );
	}

	private static function namespace_of( \WP_Ability $ability ): string {
		return explode( '/', $ability->get_name() )[0];
	}
}
