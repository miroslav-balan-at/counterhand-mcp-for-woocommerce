<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Descriptors;

use AgentGateMcp\Features\WooCommerceTools\Domain\DescriptorProvider;
use AgentGateMcp\Features\WooCommerceTools\Domain\FieldProfile;
use AgentGateMcp\Features\WooCommerceTools\Domain\MetaOwner;
use AgentGateMcp\Features\WooCommerceTools\Domain\Operation;
use AgentGateMcp\Features\WooCommerceTools\Domain\OperationDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ResourceDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ToolName;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestRoute;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Discount codes: /wc/v3/coupons.
 *
 * The field lists below are names only. What each name means, what type it
 * takes, which values it allows and how WooCommerce describes it are all read
 * off the live route at runtime — so a WooCommerce release that adds a coupon
 * field needs no edit here, and one that removes a field cannot leave this file
 * advertising it.
 */
final readonly class CouponDescriptors implements DescriptorProvider {

	/**
	 * Enough to decide which coupon you meant, and nothing that costs a query
	 * per row. A full coupon carries the product and category id lists, which
	 * are unbounded and useless in a list.
	 */
	private const LIST_FIELDS = [
		'id',
		'code',
		'amount',
		'discount_type',
		'date_expires',
		'usage_count',
		'usage_limit',
		'description',
	];

	/** Everything worth reading about one coupon, without the meta and HAL noise. */
	private const ITEM_FIELDS = [
		'id',
		'code',
		'amount',
		'discount_type',
		'description',
		'date_created',
		'date_expires',
		'usage_count',
		'individual_use',
		'product_ids',
		'excluded_product_ids',
		'usage_limit',
		'usage_limit_per_user',
		'limit_usage_to_x_items',
		'free_shipping',
		'product_categories',
		'excluded_product_categories',
		'exclude_sale_items',
		'minimum_amount',
		'maximum_amount',
		'email_restrictions',
		'used_by',
	];

	/** The fields a coupon is actually authored with. */
	private const WRITABLE_FIELDS = [
		'code',
		'amount',
		'discount_type',
		'description',
		'date_expires',
		'individual_use',
		'product_ids',
		'excluded_product_ids',
		'usage_limit',
		'usage_limit_per_user',
		'limit_usage_to_x_items',
		'free_shipping',
		'product_categories',
		'excluded_product_categories',
		'exclude_sale_items',
		'minimum_amount',
		'maximum_amount',
		'email_restrictions',
	];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			new ResourceDescriptor(
				'coupons',
				ToolGroup::Coupons,
				RestRoute::wc( '/coupons' ),
				RestRoute::wc( '/coupons/{id}' ),
				'coupon',
				'coupons',
				$this->operations(),
				null,
				null,
				MetaOwner::Post
			),
		];
	}

	/** @return list<OperationDescriptor> */
	private function operations(): array {
		return [
			new OperationDescriptor(
				ToolName::from( 'get_coupons' ),
				Operation::GetItems,
				new FieldProfile(
					[ 'page', 'per_page', 'search', 'code', 'after', 'before', 'order', 'orderby', 'include', 'exclude' ],
					self::LIST_FIELDS,
					false
				),
				'Pass code to look up one coupon by its exact code — coupon codes are stored lower-case, and WooCommerce matches them that way.'
			),
			new OperationDescriptor(
				ToolName::from( 'get_coupon' ),
				Operation::GetItem,
				new FieldProfile( [], self::ITEM_FIELDS, false ),
				'used_by lists the customers who have redeemed it.'
			),
			new OperationDescriptor(
				ToolName::from( 'create_coupon' ),
				Operation::CreateItem,
				new FieldProfile( self::WRITABLE_FIELDS, self::ITEM_FIELDS ),
				'code is required and must be unique. amount is a decimal string read according to discount_type: a currency amount for fixed discounts, a percentage for percent.'
			),
			new OperationDescriptor(
				ToolName::from( 'update_coupon' ),
				Operation::UpdateItem,
				new FieldProfile( self::WRITABLE_FIELDS, self::ITEM_FIELDS ),
				'Lists such as product_ids replace the stored value rather than adding to it, so send the full list you want to end up with.'
			),
			new OperationDescriptor(
				ToolName::from( 'delete_coupon' ),
				Operation::DeleteItem,
				new FieldProfile( [ 'force' ], [ 'id', 'code' ], false ),
				'A trashed coupon stops applying at checkout, so trashing is enough to disable one.'
			),
		];
	}
}
