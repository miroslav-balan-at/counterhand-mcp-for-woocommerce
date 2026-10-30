<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The set of scopes a token grants. Unknown scope strings are silently
 * dropped, so a stale or tampered row can only ever narrow access (fail-closed).
 */
final readonly class GrantedScopeSet {

	/** @param list<ScopeInterface> $scopes */
	private function __construct( private array $scopes ) {}

	public static function from_csv( string $csv ): self {
		$scopes = [];

		foreach ( explode( ',', $csv ) as $candidate ) {
			$scope = ApiScope::tryFrom( trim( $candidate ) ) ?? IntegrationScope::try_from_string( trim( $candidate ) );
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

	public function contains( ScopeInterface $scope ): bool {
		return in_array( $scope->key(), array_map( static fn ( ScopeInterface $held ): string => $held->key(), $this->scopes ), true );
	}

	public function is_empty(): bool {
		return [] === $this->scopes;
	}

	public function to_csv(): string {
		return implode( ',', array_map( static fn ( ScopeInterface $scope ): string => $scope->key(), $this->scopes ) );
	}

	/** @return list<ScopeInterface> */
	public function all(): array {
		return $this->scopes;
	}
}
