<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The set of scopes a token grants. Unknown scope strings are silently
 * dropped, so a stale or tampered row can only ever narrow access (fail-closed).
 */
final readonly class GrantedScopeSet {

	/** @param list<ApiScope> $scopes */
	private function __construct( private array $scopes ) {}

	public static function from_csv( string $csv ): self {
		$scopes = [];

		foreach ( explode( ',', $csv ) as $candidate ) {
			$scope = ApiScope::tryFrom( trim( $candidate ) );
			if ( null !== $scope ) {
				$scopes[ $scope->value ] = $scope;
			}
		}

		return new self( array_values( $scopes ) );
	}

	/** @param list<string> $values */
	public static function from_values( array $values ): self {
		return self::from_csv( implode( ',', $values ) );
	}

	public function contains( ApiScope $scope ): bool {
		return in_array( $scope, $this->scopes, true );
	}

	public function is_empty(): bool {
		return [] === $this->scopes;
	}

	public function to_csv(): string {
		return implode( ',', array_map( static fn ( ApiScope $scope ): string => $scope->value, $this->scopes ) );
	}

	/** @return list<ApiScope> */
	public function all(): array {
		return $this->scopes;
	}
}
