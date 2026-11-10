<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Rewrites;

defined( 'ABSPATH' ) || exit;

/**
 * Only fields update_product writes and undo can put back. Alt text needs the image
 * itself, and SEO meta goes through other plugins' tools, which keep no undo history.
 */
enum RewriteField: string {

	case Description      = 'description';
	case ShortDescription = 'short_description';

	public function label(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Description      => __( 'Description', 'counterhand-mcp-for-woocommerce' ),
			self::ShortDescription => __( 'Short description', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	public function model_noun(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Description      => 'description',
			self::ShortDescription => 'short description',
		};
	}
}
