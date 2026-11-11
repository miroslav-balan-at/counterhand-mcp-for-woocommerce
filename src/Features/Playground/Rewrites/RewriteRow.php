<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Rewrites;

defined( 'ABSPATH' ) || exit;

/** One line of the review table: the item, plus what the screen can still link to. */
final readonly class RewriteRow {

	/** @param string|null $edit_url Null when the person may not edit the product, which is not the same as it being gone. */
	public function __construct(
		public RewriteItem $item,
		public bool $product_exists,
		public ?string $edit_url,
	) {}
}
