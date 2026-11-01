<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ShipmentTrackingPolicy;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;

defined( 'ABSPATH' ) || exit;

/**
 * Tracking numbers on orders. The plugin's abilities are read-only and are skipped by the
 * bridge, so one grant covers reading and adding them.
 *
 * Probed on /orders, because the tracking routes need an order id and the provider route
 * lets anyone in, while WooCommerce holds these to what it holds orders to.
 */
final readonly class ShipmentTrackingDescriptors implements DescriptorProvider {

	private const NAMESPACE = 'wc-shipment-tracking/v3';

	private const PROVIDERS = '/shipment-trackings/providers';

	private const OUTPUT = [ 'tracking_id', 'tracking_provider', 'tracking_link', 'tracking_number', 'date_shipped' ];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		$orders = RestRoute::wc( '/orders' );

		return [
			new ResourceDescriptor(
				'shipment_trackings',
				$this->group(),
				RestRoute::of( self::NAMESPACE, '/orders/{order_id}/shipment-trackings' ),
				RestRoute::of( self::NAMESPACE, '/orders/{order_id}/shipment-trackings/{id}' ),
				'tracking number',
				'tracking numbers',
				[
					new OperationDescriptor(
						ToolName::from( 'get_order_trackings' ),
						Operation::GetItems,
						new FieldProfile( [], self::OUTPUT, false ),
						description_override: 'List the tracking numbers on one order, by order_id.'
					),
					new OperationDescriptor(
						ToolName::from( 'add_order_tracking' ),
						Operation::CreateItem,
						new FieldProfile( [ 'tracking_provider', 'custom_tracking_provider', 'custom_tracking_link', 'tracking_number', 'date_shipped' ], self::OUTPUT, false ),
						description_override: 'Add a tracking number to an order. Customers see it in their account and in the order emails sent after this; adding one sends no email by itself and does not change the order\'s status. Name a carrier from get_tracking_providers, or custom_tracking_provider with custom_tracking_link, in which %1$s stands for the number. date_shipped defaults to today.',
						requires_confirmation: true,
						policy: new ShipmentTrackingPolicy( '/' . self::NAMESPACE . self::PROVIDERS )
					),
					new OperationDescriptor(
						ToolName::from( 'delete_order_tracking' ),
						Operation::DeleteItem,
						new FieldProfile( [], [ 'tracking_id' ], false ),
						description_override: 'Remove a tracking number from an order, by order_id and tracking_id as id. Customers stop seeing it.',
						requires_confirmation: true
					),
				],
				$orders,
				$orders
			),
			new ResourceDescriptor(
				'shipment_tracking_providers',
				$this->group(),
				RestRoute::of( self::NAMESPACE, self::PROVIDERS ),
				null,
				'carrier',
				'carriers',
				[
					new OperationDescriptor(
						ToolName::from( 'get_tracking_providers' ),
						Operation::GetItems,
						new FieldProfile( [], [], false ),
						description_override: 'List the carriers this store knows, by country, with their tracking links.'
					),
				],
				$orders
			),
		];
	}

	private function group(): IntegrationGroup {
		return IntegrationGroup::of( 'woocommerce-shipment-tracking', copy: new ShipmentTrackingCopy() );
	}
}
