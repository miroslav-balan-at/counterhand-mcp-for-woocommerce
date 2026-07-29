<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The three things worth doing to a resource's custom fields.
 *
 * Three rather than techspawn's four. WooCommerce's meta_data is an upsert —
 * verified on 10.9.4: sending one key leaves every other key untouched, and
 * sending a key with no value deletes it, which is what the REST API
 * documentation prescribes. So "create" and "update" are one operation
 * wearing two names, and shipping both would tell an agent that create fails
 * on an existing key when it does not.
 */
enum MetaOperation: string {
	case Get    = 'get';
	case Set    = 'set';
	case Delete = 'delete';

	public function intent(): ToolIntent {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Get            => ToolIntent::Read,
			self::Set, self::Delete => ToolIntent::Write,
		};
	}

	/** The tool name, e.g. Set + "product" => "set_product_meta". */
	public function tool_name( string $singular ): string {
		return $this->value . '_' . $singular . '_meta'; // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}
}
