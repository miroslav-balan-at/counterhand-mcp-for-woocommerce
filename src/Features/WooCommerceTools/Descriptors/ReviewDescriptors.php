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
 * Customer reviews: /wc/v3/products/reviews.
 *
 * Two things make this group worth keeping separate from Products. Reviews
 * carry reviewer names and email addresses, so reading them is reading personal
 * data; and approving one publishes a stranger's words on the storefront, which
 * is a different kind of act from editing a price.
 *
 * WooCommerce gates these unusually — moderate_comments to read, edit_products
 * to write — so the capability probe, not a rule of ours, is what decides
 * whether a given user sees them.
 */
final readonly class ReviewDescriptors implements DescriptorProvider {

	private const LIST_FIELDS = [
		'id',
		'product_id',
		'product_name',
		'status',
		'reviewer',
		'rating',
		'verified',
		'date_created',
	];

	private const ITEM_FIELDS = [
		'id',
		'product_id',
		'product_name',
		'status',
		'reviewer',
		'reviewer_email',
		'review',
		'rating',
		'verified',
		'date_created',
	];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			new ResourceDescriptor(
				'product_reviews',
				ToolGroup::Reviews,
				RestRoute::wc( '/products/reviews' ),
				RestRoute::wc( '/products/reviews/{id}' ),
				'product review',
				'reviews',
				$this->operations()
			),
		];
	}

	/** @return list<OperationDescriptor> */
	private function operations(): array {
		return [
			new OperationDescriptor(
				ToolName::from( 'get_product_reviews' ),
				Operation::GetItems,
				new FieldProfile(
					[ 'page', 'per_page', 'search', 'after', 'before', 'product', 'status', 'reviewer', 'reviewer_email', 'order', 'orderby', 'include', 'exclude' ],
					self::LIST_FIELDS,
					false
				),
				'Pass status=hold to find reviews waiting on moderation. reviewer_email is withheld from the list; get_product_review returns it for one review.'
			),
			new OperationDescriptor(
				ToolName::from( 'get_product_review' ),
				Operation::GetItem,
				new FieldProfile( [], self::ITEM_FIELDS, false ),
				'Includes the reviewer\'s email address. That is personal data — quote it back only if the user\'s question needs it.'
			),
			new OperationDescriptor(
				ToolName::from( 'create_product_review' ),
				Operation::CreateItem,
				new FieldProfile(
					[ 'product_id', 'review', 'reviewer', 'reviewer_email', 'rating', 'status' ],
					self::ITEM_FIELDS
				),
				'SAFETY: this writes a review in someone else\'s name. Only use it to migrate reviews the store already holds, never to invent one.'
			),
			new OperationDescriptor(
				ToolName::from( 'update_product_review' ),
				Operation::UpdateItem,
				new FieldProfile( [ 'review', 'reviewer', 'reviewer_email', 'rating', 'status' ], self::ITEM_FIELDS ),
				'Setting status to approved publishes the review on the storefront; hold takes it back down. Editing the text of a real customer\'s review changes what they are recorded as having said.'
			),
			new OperationDescriptor(
				ToolName::from( 'delete_product_review' ),
				Operation::DeleteItem,
				new FieldProfile( [ 'force' ], [ 'id', 'reviewer', 'rating' ], false ),
				'SAFETY: without force the review goes to the trash. Prefer status "hold" to unpublish something without destroying it.'
			),
		];
	}
}
