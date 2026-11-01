<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Shipment Tracking answers success to a carrier it does not know, and stores the number
 * with no carrier and no link. A custom link is sent to customers, so it must at least
 * be https; the plugin itself only drops schemes such as javascript:.
 */
final readonly class ShipmentTrackingPolicy implements ArgumentPolicy {

	private const PROVIDERS_SUFFIX = '/shipment-trackings/providers';

	public function __construct( private string $providers_route ) {}

	public function verdict( array $arguments ): Verdict {
		$provider = (string) ( $arguments['tracking_provider'] ?? '' );
		$custom   = (string) ( $arguments['custom_tracking_provider'] ?? '' );

		$number = (string) ( $arguments['tracking_number'] ?? '' );

		if ( '' === $number ) {
			return Verdict::deny( 'tracking_number is required.' );
		}

		$shipped = (string) ( $arguments['date_shipped'] ?? '' );

		if ( '' !== $shipped && 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $shipped ) ) {
			return Verdict::deny( 'date_shipped must be YYYY-MM-DD. Anything else would quietly be saved as today.' );
		}

		if ( $this->is_on_order( $number, $arguments['order_id'] ?? null ) ) {
			return Verdict::deny( sprintf( '%s is already on this order, and customers would see it twice.', $number ) );
		}

		if ( '' === $provider && '' === $custom ) {
			return Verdict::deny( 'Name a carrier: tracking_provider from get_tracking_providers, or custom_tracking_provider with custom_tracking_link.' );
		}

		if ( '' !== $provider && ! $this->is_known( $provider ) ) {
			return Verdict::deny( sprintf( '"%s" is not a carrier this store knows, and it would be saved with no carrier and no link. Use a name from get_tracking_providers, or custom_tracking_provider with custom_tracking_link.', $provider ) );
		}

		$link = (string) ( $arguments['custom_tracking_link'] ?? '' );

		if ( '' !== $custom && '' === $link ) {
			return Verdict::deny( 'custom_tracking_provider needs a custom_tracking_link starting with https://, or customers get a number with nowhere to use it.' );
		}

		if ( '' !== $link && ! str_starts_with( strtolower( $link ), 'https://' ) ) {
			return Verdict::deny( 'custom_tracking_link is sent to customers, so it must start with https://.' );
		}

		return Verdict::allow();
	}

	private function is_on_order( string $number, mixed $order_id ): bool {
		if ( ! is_numeric( $order_id ) ) {
			return false;
		}

		$existing = rest_do_request( new \WP_REST_Request( 'GET', str_replace( self::PROVIDERS_SUFFIX, '/orders/' . (int) $order_id . '/shipment-trackings', $this->providers_route ) ) )->get_data();

		return in_array( $number, array_column( is_array( $existing ) ? array_filter( $existing, 'is_array' ) : [], 'tracking_number' ), true );
	}

	private function is_known( string $provider ): bool {
		$countries = rest_do_request( new \WP_REST_Request( 'GET', $this->providers_route ) )->get_data();

		foreach ( is_array( $countries ) ? $countries : [] as $carriers ) {
			foreach ( array_keys( (array) $carriers ) as $name ) {
				if ( sanitize_title( (string) $name ) === sanitize_title( $provider ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
