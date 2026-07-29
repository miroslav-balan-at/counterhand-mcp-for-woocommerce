<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Descriptors;

use AgentGateMcp\Features\WooCommerceTools\Domain\DescriptorProvider;
use AgentGateMcp\Features\WooCommerceTools\Domain\FieldProfile;
use AgentGateMcp\Features\WooCommerceTools\Domain\Operation;
use AgentGateMcp\Features\WooCommerceTools\Domain\OperationDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ResourceDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ToolName;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestRoute;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress posts and pages: /wp/v2.
 *
 * The only descriptors that leave wc/v3, which the gateway supports because
 * RestRoute carries its own namespace — one gateway, two APIs, no second
 * dispatch path.
 *
 * Scoped to `post` and `page` and nothing else, and that is a security
 * decision rather than a convenience one. wp/v2 will happily serve any post
 * type registered with show_in_rest, which on a WooCommerce store includes
 * products and, on some setups, orders — reachable through a controller with
 * entirely different permission semantics from the wc/v3 one this plugin gates
 * so carefully. Exposing only these two named routes means there is no
 * post_type argument for an agent to redirect.
 *
 * wp/v2 meta is a further reason to stop here: it is register_meta-gated, so
 * only meta a plugin deliberately declared show_in_rest is writable at all.
 * That is a stronger guarantee than MetaKeyPolicy's, and it is core's, so these
 * resources declare no meta tools of their own and lean on it.
 */
final readonly class ContentDescriptors implements DescriptorProvider {

	private const LIST_FIELDS = [ 'id', 'date', 'slug', 'status', 'title', 'excerpt', 'author', 'link' ];

	private const ITEM_FIELDS = [ 'id', 'date', 'modified', 'slug', 'status', 'title', 'content', 'excerpt', 'author', 'featured_media', 'link', 'categories', 'tags' ];

	private const PAGE_ITEM_FIELDS = [ 'id', 'date', 'modified', 'slug', 'status', 'title', 'content', 'excerpt', 'author', 'featured_media', 'link', 'parent', 'menu_order' ];

	private const QUERY = [ 'page', 'per_page', 'search', 'status', 'author', 'after', 'before', 'order', 'orderby', 'slug', 'include', 'exclude' ];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			$this->posts(),
			$this->pages(),
		];
	}

	private function posts(): ResourceDescriptor {
		return new ResourceDescriptor(
			'posts',
			ToolGroup::Content,
			RestRoute::wp( '/posts' ),
			RestRoute::wp( '/posts/{id}' ),
			'post',
			'posts',
			[
				new OperationDescriptor(
					ToolName::from( 'get_posts' ),
					Operation::GetItems,
					new FieldProfile( [ ...self::QUERY, 'categories', 'tags' ], self::LIST_FIELDS, false ),
					'Blog posts, not products. Pass status=draft to find unpublished ones; the default is published only.'
				),
				new OperationDescriptor(
					ToolName::from( 'get_post' ),
					Operation::GetItem,
					new FieldProfile( [], self::ITEM_FIELDS, false ),
					'title, content and excerpt each come back as an object with a rendered field.'
				),
				new OperationDescriptor(
					ToolName::from( 'create_post' ),
					Operation::CreateItem,
					new FieldProfile(
						[ 'title', 'content', 'excerpt', 'status', 'slug', 'categories', 'tags', 'featured_media', 'date' ],
						self::ITEM_FIELDS
					),
					'SAFETY: the post is created as a draft unless you set status explicitly, so a person can read it before it is public. content takes HTML.',
					null,
					[],
					// WordPress defaults to draft already; stated here so the
					// agent is told, and so a core change cannot quietly publish.
					[ 'status' => 'draft' ]
				),
				new OperationDescriptor(
					ToolName::from( 'update_post' ),
					Operation::UpdateItem,
					new FieldProfile(
						[ 'title', 'content', 'excerpt', 'status', 'slug', 'categories', 'tags', 'featured_media', 'date' ],
						self::ITEM_FIELDS
					),
					'Only the fields you pass are changed. status "publish" makes a draft live on the site.'
				),
				new OperationDescriptor(
					ToolName::from( 'delete_post' ),
					Operation::DeleteItem,
					new FieldProfile( [ 'force' ], [ 'id', 'slug', 'status' ], false ),
					'SAFETY: without force the post goes to the trash and can be restored.'
				),
			]
		);
	}

	private function pages(): ResourceDescriptor {
		return new ResourceDescriptor(
			'pages',
			ToolGroup::Content,
			RestRoute::wp( '/pages' ),
			RestRoute::wp( '/pages/{id}' ),
			'page',
			'pages',
			[
				new OperationDescriptor(
					ToolName::from( 'get_pages' ),
					Operation::GetItems,
					new FieldProfile( [ ...self::QUERY, 'parent', 'menu_order' ], self::LIST_FIELDS, false ),
					'Includes the WooCommerce pages — cart, checkout, my account — so take care before editing one you did not create.'
				),
				new OperationDescriptor(
					ToolName::from( 'get_page' ),
					Operation::GetItem,
					new FieldProfile( [], self::PAGE_ITEM_FIELDS, false )
				),
				new OperationDescriptor(
					ToolName::from( 'create_page' ),
					Operation::CreateItem,
					new FieldProfile(
						[ 'title', 'content', 'excerpt', 'status', 'slug', 'parent', 'menu_order', 'featured_media' ],
						self::PAGE_ITEM_FIELDS
					),
					'SAFETY: created as a draft unless you set status explicitly.',
					null,
					[],
					[ 'status' => 'draft' ]
				),
				new OperationDescriptor(
					ToolName::from( 'update_page' ),
					Operation::UpdateItem,
					new FieldProfile(
						[ 'title', 'content', 'excerpt', 'status', 'slug', 'parent', 'menu_order', 'featured_media' ],
						self::PAGE_ITEM_FIELDS
					),
					'SAFETY: cart, checkout and my-account are pages. Editing the content of one can break checkout, so read it first and be sure which page you have.'
				),
				new OperationDescriptor(
					ToolName::from( 'delete_page' ),
					Operation::DeleteItem,
					new FieldProfile( [ 'force' ], [ 'id', 'slug', 'status' ], false ),
					'SAFETY: trashing the cart or checkout page stops customers buying anything. Confirm which page this is before calling it.'
				),
			]
		);
	}
}
