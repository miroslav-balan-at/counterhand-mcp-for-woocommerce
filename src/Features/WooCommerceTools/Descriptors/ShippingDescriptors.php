<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Delivery: zones, the regions each covers, and the methods offered inside them.
 *
 * Three nested resources, and the nesting is the whole model — a rate is not a
 * property of a zone but of a *method instance* inside one, which is why
 * changing postage means walking zone to method rather than editing one record.
 *
 * The locations resource is the odd one: WooCommerce addresses a zone's whole
 * location set at once, so its "item" route is the same path as its collection
 * and an update replaces every location rather than adding to them.
 */
final readonly class ShippingDescriptors implements DescriptorProvider {

	private const ZONE_FIELDS = [ 'id', 'name', 'order' ];

	private const LOCATION_FIELDS = [ 'code', 'type' ];

	private const METHOD_INSTANCE_FIELDS = [ 'instance_id', 'title', 'order', 'enabled', 'method_id', 'method_title', 'method_description', 'settings' ];

	private const METHOD_FIELDS = [ 'id', 'title', 'description' ];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			$this->zones(),
			$this->zone_locations(),
			$this->zone_methods(),
			$this->available_methods(),
		];
	}

	private function zones(): ResourceDescriptor {
		return new ResourceDescriptor(
			'shipping_zones',
			ToolGroup::Shipping,
			RestRoute::wc( '/shipping/zones' ),
			RestRoute::wc( '/shipping/zones/{id}' ),
			'shipping zone',
			'shipping_zones',
			[
				new OperationDescriptor(
					ToolName::from( 'get_shipping_zones' ),
					Operation::GetItems,
					new FieldProfile( [], self::ZONE_FIELDS, false ),
					'A zone is a set of regions plus the delivery methods offered there. Zone 0, "Locations not covered by your other zones", is WooCommerce\'s fallback and always exists.'
				),
				new OperationDescriptor(
					ToolName::from( 'get_shipping_zone' ),
					Operation::GetItem,
					new FieldProfile( [], self::ZONE_FIELDS, false ),
					'Use get_shipping_zone_locations for the regions it covers and get_shipping_zone_methods for what it offers.'
				),
				new OperationDescriptor(
					ToolName::from( 'create_shipping_zone' ),
					Operation::CreateItem,
					new FieldProfile( [ 'name', 'order' ], self::ZONE_FIELDS ),
					'A new zone covers nowhere and offers nothing until you set its locations and add a method.'
				),
				new OperationDescriptor(
					ToolName::from( 'update_shipping_zone' ),
					Operation::UpdateItem,
					new FieldProfile( [ 'name', 'order' ], self::ZONE_FIELDS ),
					'order decides which zone wins when several match an address — lower is checked first.'
				),
				new OperationDescriptor(
					ToolName::from( 'delete_shipping_zone' ),
					Operation::DeleteItem,
					new FieldProfile( [ 'force' ], self::ZONE_FIELDS, false ),
					'SAFETY: this removes the delivery options for everyone in that zone, so those customers fall through to whichever zone matches next. force is required.'
				),
			]
		);
	}

	/**
	 * GET and PUT on one path, with no per-location addressing — so collection
	 * and item are the same route and "update" means "replace the whole set".
	 */
	private function zone_locations(): ResourceDescriptor {
		return new ResourceDescriptor(
			'shipping_zone_locations',
			ToolGroup::Shipping,
			RestRoute::wc( '/shipping/zones/{id}/locations' ),
			RestRoute::wc( '/shipping/zones/{id}/locations' ),
			'shipping zone location set',
			'shipping_zone_locations',
			[
				new OperationDescriptor(
					ToolName::from( 'get_shipping_zone_locations' ),
					Operation::GetItems,
					new FieldProfile( [], self::LOCATION_FIELDS, false ),
					'',
					'The regions one shipping zone covers, identified by the zone id. Each entry is a code and a type: country, state, postcode or continent.'
				),
				/*
				 * The only operation in the surface whose controller reads the raw
				 * JSON body: it takes a bare array of locations, and a params-only
				 * request looks to it like an empty list — which it obeys, wiping
				 * the zone and returning 200.
				 */
				new OperationDescriptor(
					ToolName::from( 'update_shipping_zone_locations' ),
					Operation::UpdateItem,
					new FieldProfile( [], self::LOCATION_FIELDS ),
					'',
					'Replace the regions a zone covers. This is a replacement, not an addition: send the complete list you want the zone to end up with in locations, because anything you leave out stops being covered.',
					body_argument: 'locations',
					body_schema: [
						'type'        => 'array',
						'description' => 'The complete set of regions this zone should cover.',
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'code' => [
									'type'        => 'string',
									'description' => 'Region code: a country ("AT"), a state ("US:CA"), a continent ("EU") or a postcode.',
								],
								'type' => [
									'type'        => 'string',
									'enum'        => [ 'country', 'state', 'continent', 'postcode' ],
									'default'     => 'country',
									'description' => 'What the code names.',
								],
							],
							'required'   => [ 'code' ],
						],
					],
				),
			]
		);
	}

	private function zone_methods(): ResourceDescriptor {
		return new ResourceDescriptor(
			'shipping_zone_methods',
			ToolGroup::Shipping,
			RestRoute::wc( '/shipping/zones/{zone_id}/methods' ),
			RestRoute::wc( '/shipping/zones/{zone_id}/methods/{instance_id}' ),
			'shipping zone method',
			'shipping_zone_methods',
			[
				new OperationDescriptor(
					ToolName::from( 'get_shipping_zone_methods' ),
					Operation::GetItems,
					new FieldProfile( [], self::METHOD_INSTANCE_FIELDS, false ),
					'',
					'The delivery methods offered in one zone, identified by zone_id. settings holds the rates: this is where a zone\'s postage cost actually lives.'
				),
				new OperationDescriptor(
					ToolName::from( 'get_shipping_zone_method' ),
					Operation::GetItem,
					new FieldProfile( [], self::METHOD_INSTANCE_FIELDS, false ),
					'instance_id identifies this method inside the zone, and is not the same as method_id, which says what kind of method it is.'
				),
				new OperationDescriptor(
					ToolName::from( 'create_shipping_zone_method' ),
					Operation::CreateItem,
					new FieldProfile( [ 'method_id', 'order', 'enabled', 'settings' ], self::METHOD_INSTANCE_FIELDS ),
					'method_id names the kind of method to add — get_shipping_methods lists what this store has available. Adding one makes it selectable at checkout for that zone straight away.'
				),
				new OperationDescriptor(
					ToolName::from( 'update_shipping_zone_method' ),
					Operation::UpdateItem,
					new FieldProfile( [ 'order', 'enabled', 'settings' ], self::METHOD_INSTANCE_FIELDS ),
					'SAFETY: changing settings changes what customers are charged for delivery. Pass enabled=false to take a method out of use without deleting its configuration.'
				),
				new OperationDescriptor(
					ToolName::from( 'delete_shipping_zone_method' ),
					Operation::DeleteItem,
					new FieldProfile( [ 'force' ], [ 'instance_id', 'title', 'method_id' ], false ),
					'SAFETY: deleting the last method in a zone leaves customers there with no way to check out. Prefer enabled=false.'
				),
			]
		);
	}

	/** What kinds of method this install offers at all. Read-only in WooCommerce. */
	private function available_methods(): ResourceDescriptor {
		return new ResourceDescriptor(
			'shipping_methods',
			ToolGroup::Shipping,
			RestRoute::wc( '/shipping_methods' ),
			RestRoute::wc( '/shipping_methods/{id}' ),
			'shipping method type',
			'shipping_methods',
			[
				new OperationDescriptor(
					ToolName::from( 'get_shipping_methods' ),
					Operation::GetItems,
					new FieldProfile( [], self::METHOD_FIELDS, false ),
					'',
					'The kinds of delivery method this store has installed — flat rate, free shipping, local pickup and whatever plugins add. These are the types available, not methods in use; pass one\'s id as method_id to create_shipping_zone_method.'
				),
				new OperationDescriptor(
					ToolName::from( 'get_shipping_method' ),
					Operation::GetItem,
					new FieldProfile( [], self::METHOD_FIELDS, false )
				),
			]
		);
	}
}
