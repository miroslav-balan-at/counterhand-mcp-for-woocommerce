<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Germanized answers success to a term it cannot find. A delivery time with an unknown
 * id creates a new one named after that id, which customers then see. Any other term
 * is cleared, and so is anything sent with only a name, such as {"name": "2-3 days"}.
 *
 * Whether the term exists is asked of its own list route, which also holds it to that
 * list: an id from another taxonomy is not found there.
 */
final readonly class LegalDetailsPolicy implements ArgumentPolicy {

	private const TERM_ROUTES = [
		'delivery_time'            => '/wc/v3/products/delivery_times/',
		'sale_price_label'         => '/wc/v3/products/price_labels/',
		'sale_price_regular_label' => '/wc/v3/products/price_labels/',
		'manufacturer'             => '/wc/v3/products/manufacturers/',
		'unit'                     => '/wc/v3/products/units/',
	];

	public function verdict( array $arguments ): Verdict {
		$sent = [];

		foreach ( self::TERM_ROUTES as $name => $route ) {
			$sent[] = [ $name, $arguments[ $name ] ?? null, $route ];
		}

		foreach ( (array) ( $arguments['country_specific_delivery_times'] ?? [] ) as $entry ) {
			$sent[] = [ 'country_specific_delivery_times', $entry, self::TERM_ROUTES['delivery_time'] ];
		}

		if ( is_array( $arguments['food'] ?? null ) ) {
			$sent[] = [ 'food.deposit_type', $arguments['food']['deposit_type'] ?? null, '/wc/v3/products/deposit_types/' ];
		}

		foreach ( $sent as [ $name, $value, $route ] ) {
			if ( ! self::clears( $value ) && ! self::names_a_term( $value, $route ) ) {
				return Verdict::deny( sprintf( '%s must be {"id": n} with an id from its list tool, or null to clear it. Germanized would otherwise clear the value, or create a new term, and report success.', $name ) );
			}
		}

		return Verdict::allow();
	}

	/** Null reaches a policy as an empty object, because validation casts it to the declared type. */
	private static function clears( mixed $value ): bool {
		return null === $value || [] === $value;
	}

	private static function names_a_term( mixed $value, string $route ): bool {
		$id = is_array( $value ) ? ( $value['id'] ?? null ) : null;

		return is_numeric( $id ) && (int) $id > 0 && ! rest_do_request( new \WP_REST_Request( 'GET', $route . (int) $id ) )->is_error();
	}
}
