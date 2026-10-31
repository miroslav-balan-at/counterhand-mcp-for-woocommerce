<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The set of scopes a token grants. Unknown scope strings are silently
 * dropped, so a stale or tampered row can only ever narrow access (fail-closed).
 */
final readonly class GrantedScopeSet {

	/** @param array<string, ScopeInterface> $scopes By key. */
	private function __construct( private array $scopes ) {}

	/** @param list<ScopeInterface> $scopes */
	public static function of( array $scopes ): self {
		return new self( array_column( array_map( static fn ( ScopeInterface $scope ): array => [ $scope->key(), $scope ], $scopes ), 1, 0 ) );
	}

	public static function from_csv( string $csv ): self {
		$scopes = [];

		foreach ( explode( ',', $csv ) as $candidate ) {
			$scope = ApiScope::tryFrom( trim( $candidate ) ) ?? IntegrationScope::try_from_string( trim( $candidate ) );
			if ( null !== $scope ) {
				$scopes[ $scope->value ] = $scope;
			}
		}

		return new self( $scopes );
	}

	/** @param list<string> $values */
	public static function from_values( array $values ): self {
		return self::from_csv( implode( ',', $values ) );
	}

	public function contains( ScopeInterface $scope ): bool {
		return isset( $this->scopes[ $scope->key() ] );
	}

	public function is_empty(): bool {
		return [] === $this->scopes;
	}

	public function to_csv(): string {
		return implode( ',', array_map( static fn ( ScopeInterface $scope ): string => $scope->key(), $this->scopes ) );
	}

	/** @return list<ScopeInterface> */
	public function all(): array {
		return array_values( $this->scopes );
	}
}
