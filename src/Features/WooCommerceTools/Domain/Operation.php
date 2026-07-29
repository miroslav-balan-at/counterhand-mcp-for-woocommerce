<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Domain;

use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestMethod;

defined( 'ABSPATH' ) || exit;

/**
 * The five things a wc/v3 resource controller can be asked to do.
 *
 * The case values are WooCommerce's own controller method names, which is not
 * decoration: it is the vocabulary every WC_REST_Controller subclass already
 * uses for these exact five handlers, so a descriptor reading get_items lines
 * up with the method whose args become the tool's schema.
 *
 * Knowing the operation is enough to know the HTTP method, which route of the
 * resource it uses, and whether it reads or writes. A descriptor therefore
 * never restates any of the three, and cannot state them inconsistently — no
 * "delete" that quietly dispatches a GET.
 */
enum Operation: string {
	case GetItems   = 'get_items';
	case GetItem    = 'get_item';
	case CreateItem = 'create_item';
	case UpdateItem = 'update_item';
	case DeleteItem = 'delete_item';

	public function method(): RestMethod {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::GetItems, self::GetItem => RestMethod::Get,
			self::CreateItem              => RestMethod::Post,
			self::UpdateItem              => RestMethod::Put,
			self::DeleteItem              => RestMethod::Delete,
		};
	}

	public function intent(): ToolIntent {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::GetItems, self::GetItem => ToolIntent::Read,
			default                       => ToolIntent::Write,
		};
	}

	/** Whether this addresses one resource by id rather than the collection. */
	public function targets_item(): bool {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::GetItems, self::CreateItem => false,
			default                          => true,
		};
	}

	/** Whether the response is a collection rather than a single resource. */
	public function returns_collection(): bool {
		return self::GetItems === $this; // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	/**
	 * What the tool tells an agent it does.
	 *
	 * Written once here rather than a hundred times in descriptors: the sentence
	 * only ever varies by the resource's own nouns, and a descriptor that needs
	 * to say something else says it in description_override. Not translated —
	 * the audience is a model reading the MCP tool list, and the protocol has no
	 * notion of the caller's locale.
	 */
	public function describe( string $singular, string $plural ): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::GetItems   => sprintf(
				'List %s in the store. Results are paginated: pass page and per_page. The response reports total and total_pages, so you can tell up front whether another page is worth fetching.',
				$plural
			),
			self::GetItem    => sprintf( 'Fetch one %s by its numeric id.', $singular ),
			self::CreateItem => sprintf( 'Create a %s.', $singular ),
			self::UpdateItem => sprintf( 'Update an existing %s. Only the fields you pass are changed.', $singular ),
			self::DeleteItem => sprintf(
				'Delete a %s. WooCommerce moves it to the trash unless force is true, which deletes it permanently and cannot be undone.',
				$singular
			),
		};
	}
}
