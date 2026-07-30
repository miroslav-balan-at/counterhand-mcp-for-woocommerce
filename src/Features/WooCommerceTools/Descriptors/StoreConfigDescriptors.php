<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\SecretSettingPolicy;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Store configuration and payment gateways.
 *
 * The writes here are the ones that decide how a store charges money — the
 * currency, whether tax applies, which payment methods a customer sees — so
 * each carries two things the rest of the surface does not: a required
 * `confirm` argument, and a policy that refuses settings named like
 * credentials. A single "may write settings" grant is far too coarse to be the
 * last word when a Stripe secret key is a setting exactly like the shop's
 * postal address is.
 *
 * Both groups sit in ToolSection::Advanced, which renders them collapsed on the
 * consent screen and never pre-ticks them, and both ship disabled.
 */
final readonly class StoreConfigDescriptors implements DescriptorProvider {

	private const SETTING_FIELDS = [ 'id', 'label', 'description', 'type', 'default', 'value', 'options', 'group_id' ];

	private const GATEWAY_FIELDS = [ 'id', 'title', 'description', 'order', 'enabled', 'method_title', 'method_description', 'method_supports' ];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			$this->setting_groups(),
			$this->settings(),
			$this->payment_gateways(),
		];
	}

	private function setting_groups(): ResourceDescriptor {
		return new ResourceDescriptor(
			'setting_groups',
			ToolGroup::Settings,
			RestRoute::wc( '/settings' ),
			null,
			'settings group',
			'setting_groups',
			[
				new OperationDescriptor(
					ToolName::from( 'get_setting_groups' ),
					Operation::GetItems,
					new FieldProfile( [], [ 'id', 'label', 'description', 'parent_id' ], false ),
					'',
					'The sections WooCommerce organises its settings into — general, products, tax, shipping, checkout and so on. Start here, then pass a group id to get_settings.'
				),
			]
		);
	}

	/**
	 * The write probe points at the item route, against the usual rule.
	 *
	 * Ordinarily probing an item route hides a tool from everyone, because
	 * map_meta_cap() denies an id-less item capability. That hazard belongs to
	 * post-type-backed resources checked with wc_rest_check_post_permissions().
	 * Settings are checked with wc_rest_check_manager_permissions(), which is a
	 * bare capability test and reads no id at all — and WooCommerce serves no
	 * POST on the settings collection, so there is nothing else to ask.
	 *
	 * Without this the three write tools in this file are hidden from
	 * administrators, which is how it was found.
	 */
	private function settings(): ResourceDescriptor {
		return new ResourceDescriptor(
			'settings',
			ToolGroup::Settings,
			RestRoute::wc( '/settings/{group_id}' ),
			RestRoute::wc( '/settings/{group_id}/{id}' ),
			'setting',
			'settings',
			[
				new OperationDescriptor(
					ToolName::from( 'get_settings' ),
					Operation::GetItems,
					new FieldProfile( [], self::SETTING_FIELDS, false ),
					'',
					'Every setting in one group, identified by group_id, with its current value. Read this before proposing any change: it is the only way to know what a setting is set to now and, where it is a fixed choice, which values it will accept.'
				),
				new OperationDescriptor(
					ToolName::from( 'get_setting' ),
					Operation::GetItem,
					new FieldProfile( [], self::SETTING_FIELDS, false ),
					'Needs both the group_id and the setting id. options lists the allowed values where a setting is a fixed choice.'
				),
				new OperationDescriptor(
					ToolName::from( 'update_setting' ),
					Operation::UpdateItem,
					new FieldProfile( [ 'value' ], self::SETTING_FIELDS ),
					'',
					'SAFETY: this changes how the store behaves for every customer, immediately. Read the setting first with get_setting to see its current value and, where it is a fixed choice, the values it accepts. Say in plain words what you are about to change it from and to, and only then call this with confirm set to true. Settings named like an API key, secret, password or token are refused — those have to be changed by a person in the WooCommerce admin.',
					[],
					[],
					true,
					new SecretSettingPolicy()
				),
			],
			null,
			RestRoute::wc( '/settings/{group_id}/{id}' )
		);
	}

	private function payment_gateways(): ResourceDescriptor {
		return new ResourceDescriptor(
			'payment_gateways',
			ToolGroup::Gateways,
			RestRoute::wc( '/payment_gateways' ),
			RestRoute::wc( '/payment_gateways/{id}' ),
			'payment gateway',
			'payment_gateways',
			[
				new OperationDescriptor(
					ToolName::from( 'get_payment_gateways' ),
					Operation::GetItems,
					new FieldProfile( [], self::GATEWAY_FIELDS, false ),
					'',
					'The payment methods installed on this store and whether each is enabled. The settings field, which holds API keys and secrets, is deliberately not requested.'
				),
				new OperationDescriptor(
					ToolName::from( 'get_payment_gateway' ),
					Operation::GetItem,
					new FieldProfile( [], self::GATEWAY_FIELDS, false ),
					'method_supports says what the gateway can do — refunds in particular, which decides whether create_order_refund can return money through it.'
				),
				new OperationDescriptor(
					ToolName::from( 'update_payment_gateway' ),
					Operation::UpdateItem,
					// No settings field: that is where gateways keep their live
					// API credentials, and there is no version of writing those
					// through an agent that is a good idea.
					new FieldProfile( [ 'enabled', 'title', 'description', 'order' ], self::GATEWAY_FIELDS ),
					'',
					'SAFETY: enabling or disabling a gateway changes how customers can pay, immediately. Disabling the only enabled gateway leaves nobody able to check out — list them with get_payment_gateways first and say which will remain. Call with confirm set to true once the user has agreed. API keys and secrets cannot be written here at all.',
					[],
					[],
					true
				),
			],
			null,
			// Same reasoning as settings: manager permissions, no POST on the
			// collection.
			RestRoute::wc( '/payment_gateways/{id}' )
		);
	}
}
