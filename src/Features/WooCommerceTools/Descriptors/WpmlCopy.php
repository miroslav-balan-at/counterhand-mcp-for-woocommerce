<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\Tokens\Domain\IntegrationCopyInterface;

defined( 'ABSPATH' ) || exit;

final readonly class WpmlCopy implements IntegrationCopyInterface {

	public function name(): string {
		return 'WPML';
	}

	public function description(): string {
		return __( 'Translations of products, their variations and custom fields, categories, tags, attribute terms, and strings such as email subjects, through WPML and WooCommerce Multilingual.', 'counterhand-mcp-for-woocommerce' );
	}

	public function read_description(): string {
		return __( 'See which products, categories, tags and attribute terms are translated into which language, and their text, including drafts, as well as translatable strings. Payment settings are never included.', 'counterhand-mcp-for-woocommerce' );
	}

	public function write_description(): string {
		return __( 'Add translations and change their text, without being able to change an original or any price or stock. A new product translation starts as a draft, and publishing it needs explicit confirmation. Category, tag and attribute translations are visible as soon as they are saved. Setting a string, which can change what store emails say, needs explicit confirmation.', 'counterhand-mcp-for-woocommerce' );
	}
}
