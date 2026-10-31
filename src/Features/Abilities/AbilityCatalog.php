<?php

declare( strict_types=1 );

namespace Counterhand\Features\Abilities;

use Counterhand\Features\Tokens\Domain\GenericIntegrationCopy;
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

	/** A namespace's own spelling, where capitalizing its words would misname it. */
	private const NAMES = [
		'rank-math' => 'Rank Math',
		'wpforms'   => 'WPForms',
		'yoast-seo' => 'Yoast SEO',
	];

	/** @var ?list<\WP_Ability> */
	private ?array $abilities = null;

	/** @var ?array<string, IntegrationGroup> By namespace. */
	private ?array $groups = null;

	/** @return list<\WP_Ability> */
	public function abilities(): array {
		// Asking before init is an error in core, and the answer would be empty.
		if ( ! did_action( 'init' ) ) {
			return [];
		}

		return $this->abilities ??= $this->discovered();
	}

	public function group_of( \WP_Ability $ability ): ?IntegrationGroup {
		$this->groups ??= $this->built_groups();

		return $this->groups[ self::namespace_of( $ability ) ] ?? null;
	}

	/** @return list<IntegrationGroup> */
	public function groups(): array {
		// Before init abilities() is empty, and that must not be remembered.
		if ( ! did_action( 'init' ) ) {
			return [];
		}

		$this->groups ??= $this->built_groups();

		return array_values( $this->groups );
	}

	/**
	 * Registration runs every plugin's callback, and one that throws must cost only
	 * the abilities, not the screen or the endpoint asking.
	 *
	 * @return list<\WP_Ability>
	 */
	private function discovered(): array {
		try {
			return array_values( array_filter( wp_get_abilities(), [ self::class, 'is_exposed' ] ) );
		} catch ( \Throwable $throwable ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( 'Reading abilities failed: ' . $throwable->getMessage(), [ 'source' => 'counterhand-mcp' ] );
			}

			return [];
		}
	}

	/**
	 * Writable only when the plugin actually offers a write, so the settings row does
	 * not show a switch that does nothing.
	 *
	 * @return array<string, IntegrationGroup>
	 */
	private function built_groups(): array {
		$writable = [];

		foreach ( $this->abilities() as $ability ) {
			$namespace              = self::namespace_of( $ability );
			$writable[ $namespace ] = ( $writable[ $namespace ] ?? false ) || ! AbilityAnnotations::of( $ability )->read_only;
		}

		$groups = [];

		foreach ( $writable as $namespace => $is_writable ) {
			$group = IntegrationGroup::try_of( $namespace, $is_writable, new GenericIntegrationCopy( $namespace, self::NAMES[ $namespace ] ?? null ) );

			if ( null !== $group ) {
				$groups[ $namespace ] = $group;
			}
		}

		ksort( $groups );

		return $groups;
	}

	/** Plugins mostly set meta.mcp.public, core sets meta.public; either one counts. */
	private static function is_exposed( \WP_Ability $ability ): bool {
		$meta = $ability->get_meta();

		return ! in_array( self::namespace_of( $ability ), self::SKIPPED_NAMESPACES, true )
			&& ( true === ( $meta['public'] ?? null ) || true === ( $meta['mcp']['public'] ?? null ) )
			&& ! self::collides_with_confirmation( $ability );
	}

	/**
	 * The pipeline takes `confirm` away before execute(), so a gated ability declaring
	 * its own could never receive it and would fail every call. Better not offered.
	 */
	private static function collides_with_confirmation( \WP_Ability $ability ): bool {
		$properties = $ability->get_input_schema()['properties'] ?? [];

		return AbilityAnnotations::of( $ability )->destructive && is_array( $properties ) && isset( $properties['confirm'] );
	}

	private static function namespace_of( \WP_Ability $ability ): string {
		return explode( '/', $ability->get_name() )[0];
	}
}
