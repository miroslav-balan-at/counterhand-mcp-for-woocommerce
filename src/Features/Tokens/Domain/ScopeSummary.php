<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * A granted scope set, said the way a person would say it.
 *
 * Rendering raw scope values worked at six of them. At thirty a connections
 * table becomes a paragraph of slugs that nobody reads, which defeats the point
 * of showing the grant at all — so the two axes collapse per group, the groups
 * come back in catalogue order, and the tail is counted rather than listed.
 */
final readonly class ScopeSummary {

	/** @param list<ScopeGrant> $grants */
	private function __construct(
		public array $grants,
	) {}

	public static function of( GrantedScopeSet $scopes ): self {
		$grants = [];

		// ToolGroup order, not grant order: the same token always reads the same
		// way, and a re-issued token does not appear to have changed. Integrations
		// come from the grant itself, so one whose plugin is off is still shown.
		foreach ( [ ...ToolGroup::cases(), ...self::integrations_in( $scopes ) ] as $group ) {
			$write = $group->write_scope();

			if ( ! $scopes->contains( $group->read_scope() ) && ( null === $write || ! $scopes->contains( $write ) ) ) {
				continue;
			}

			$grants[] = new ScopeGrant( $group, null !== $write && $scopes->contains( $write ) );
		}

		return new self( $grants );
	}

	/** @return list<ScopeGroupInterface> */
	private static function integrations_in( GrantedScopeSet $scopes ): array {
		$groups = [];

		foreach ( $scopes->all() as $scope ) {
			if ( ToolSection::Integrations === $scope->group()->section() ) {
				$groups[ $scope->group()->key() ] = ScopeGroups::try_from( $scope->group()->key() ) ?? $scope->group();
			}
		}

		ksort( $groups );

		return array_values( $groups );
	}

	public function is_empty(): bool {
		return [] === $this->grants;
	}

	/**
	 * The grants worth spelling out, at most $limit of them.
	 *
	 * @return list<ScopeGrant>
	 */
	public function shown( int $limit ): array {
		return array_slice( $this->grants, 0, $limit );
	}

	/** How many grants shown() left out, for a "+N more" tail. */
	public function hidden( int $limit ): int {
		return max( 0, count( $this->grants ) - $limit );
	}

	/**
	 * What the token can reach right now, for an agent: naming a plugin that is off
	 * would promise work it cannot do. labels() keeps it, for the person reviewing a grant.
	 *
	 * @return list<string>
	 */
	public function live_labels(): array {
		return array_values(
			array_map(
				static fn ( ScopeGrant $grant ): string => $grant->label(),
				array_filter( $this->grants, static fn ( ScopeGrant $grant ): bool => ScopeGroups::is_live( $grant->group ) )
			)
		);
	}

	/** @return list<string> */
	public function labels(): array {
		return array_map( static fn ( ScopeGrant $grant ): string => $grant->label(), $this->grants );
	}
}
