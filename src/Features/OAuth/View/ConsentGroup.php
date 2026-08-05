<?php

declare( strict_types=1 );

namespace Counterhand\Features\OAuth\View;

use Counterhand\Features\Settings\PublishedScopes;
use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * One row of the consent screen: a tool group, and whichever of its two scopes
 * the client actually asked for.
 *
 * Grouping the two axes into one row is what keeps the screen readable as the
 * catalogue grows. A flat scope list reads fine at six entries and becomes an
 * unskimmable wall at thirty, which is the state in which people stop reading
 * and just approve.
 */
final readonly class ConsentGroup {

	/** @param list<ConsentScope> $scopes */
	private function __construct(
		public ToolGroup $group,
		public array $scopes,
	) {}

	/**
	 * Every scope the group has, whether grantable or not — the screen shows
	 * the catalogue, and availability says why a box is inert.
	 *
	 * @param  list<ApiScope> $requested
	 */
	public static function from( ToolGroup $group, array $requested, PublishedScopes $published ): self {
		$scopes = [];

		foreach ( [ $group->read_scope(), $group->write_scope() ] as $scope ) {
			if ( null === $scope ) {
				continue;
			}

			$availability = match ( true ) {
				! $published->includes( $scope )        => ConsentAvailability::SwitchedOff,
				! in_array( $scope, $requested, true )  => ConsentAvailability::NotRequested,
				default                                 => ConsentAvailability::Grantable,
			};

			$scopes[] = new ConsentScope(
				scope: $scope,
				availability: $availability,
				// Advanced groups never start ticked: granting one should take
				// a deliberate click, not the absence of an untick.
				pre_checked: ConsentAvailability::Grantable === $availability && ! $group->section()->is_advanced(),
			);
		}

		return new self( $group, $scopes );
	}

	/** Only a grantable write warrants the "can change your store" warning. */
	public function has_write(): bool {
		foreach ( $this->scopes as $scope ) {
			if ( $scope->available() && $scope->scope->is_write() ) {
				return true;
			}
		}

		return false;
	}
}
