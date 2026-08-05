<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

use Counterhand\Features\Tokens\Domain\ApiScope;

defined( 'ABSPATH' ) || exit;

/**
 * The scopes this store currently offers: the scope catalogue filtered by the
 * group toggles in Settings.
 *
 * Every stage of the OAuth conversation reads this — discovery, the consent
 * screen, and the moment a grant is minted. Before it existed, discovery
 * advertised the whole catalogue, so a client could request a disabled group's
 * scope, the admin could approve it, and the resulting token silently did
 * nothing for it: ToolRegistry (correctly) refused the tools. A permission that
 * can be granted but never works is worse than one that is never offered.
 *
 * The rule mirrors ToolRegistry exactly — read axis follows the group's read
 * toggle, write axis its write toggle — so what discovery promises is what
 * tools/list delivers.
 */
final readonly class PublishedScopes {

	public function __construct( private PluginSettings $settings ) {}

	/** @return list<ApiScope> */
	public function all(): array {
		return array_values(
			array_filter(
				ApiScope::cases(),
				fn ( ApiScope $scope ): bool => $this->includes( $scope )
			)
		);
	}

	/** @return list<string> */
	public function values(): array {
		return array_map( static fn ( ApiScope $scope ): string => $scope->value, $this->all() );
	}

	public function includes( ApiScope $scope ): bool {
		return $scope->is_write()
			? $this->settings->is_group_write_enabled( $scope->group() )
			: $this->settings->is_group_read_enabled( $scope->group() );
	}

	/**
	 * The part of a request this store can actually honour.
	 *
	 * @param  list<ApiScope> $requested
	 * @return list<ApiScope>
	 */
	public function grantable( array $requested ): array {
		return array_values(
			array_filter( $requested, fn ( ApiScope $scope ): bool => $this->includes( $scope ) )
		);
	}

	/**
	 * The part it cannot — what the consent screen owes the admin an
	 * explanation for, rather than dropping silently.
	 *
	 * @param  list<ApiScope> $requested
	 * @return list<ApiScope>
	 */
	public function withheld( array $requested ): array {
		return array_values(
			array_filter( $requested, fn ( ApiScope $scope ): bool => ! $this->includes( $scope ) )
		);
	}
}
