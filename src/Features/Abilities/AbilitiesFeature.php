<?php

declare( strict_types=1 );

namespace Counterhand\Features\Abilities;

use Counterhand\Features\McpServer\ToolRegistrarInterface;
use Counterhand\Features\Tokens\Domain\ScopeGroups;
use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes other plugins' WordPress abilities (an API in core since 6.9) as integrations.
 *
 * Lazy on both paths, because asking for the abilities runs every plugin's
 * registration callback, which core itself defers until somebody needs them.
 */
final readonly class AbilitiesFeature implements FeatureInterface {

	public function __construct(
		private ToolRegistrarInterface $tool_registry,
		private AbilityCatalog $catalog,
	) {}

	public function register(): void {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return;
		}

		add_filter( ScopeGroups::INTEGRATIONS_FILTER, [ $this, 'with_groups' ] );
		add_filter( ScopeGroups::SUPPORTED_PLUGINS_FILTER, [ $this, 'with_known_names' ] );
		$this->tool_registry->add_late_source( [ $this, 'tools' ] );
	}

	/**
	 * @param  mixed $names What earlier filters returned.
	 * @return array<mixed>
	 */
	public function with_known_names( mixed $names ): array {
		return [ ...( is_array( $names ) ? $names : [] ), ...KnownPluginCopy::known_names() ];
	}

	/**
	 * @param  mixed $groups What earlier filters returned.
	 * @return array<mixed>
	 */
	public function with_groups( mixed $groups ): array {
		return [ ...( is_array( $groups ) ? $groups : [] ), ...$this->catalog->groups() ];
	}

	/** @return \Generator<AbilityTool> */
	public function tools(): \Generator {
		foreach ( $this->catalog->abilities() as $ability ) {
			$group = $this->catalog->group_of( $ability );

			if ( null !== $group ) {
				yield new AbilityTool( $ability, $group, $this->catalog->policy_of( $ability ) );
			}
		}
	}
}
