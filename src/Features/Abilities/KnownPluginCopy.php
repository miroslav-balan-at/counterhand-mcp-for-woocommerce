<?php

declare( strict_types=1 );

namespace Counterhand\Features\Abilities;

use Counterhand\Features\Tokens\Domain\GenericIntegrationCopy;
use Counterhand\Features\Tokens\Domain\IntegrationCopyInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Consent wording for plugins whose abilities reach further than their name says,
 * from reading what each one does. Anything else gets the generic wording.
 */
final readonly class KnownPluginCopy implements IntegrationCopyInterface {

	private const NAMES = [
		'rank-math' => 'Rank Math',
		'wpforms'   => 'WPForms',
		'yoast-seo' => 'Yoast SEO',
	];

	public function __construct( private string $slug ) {}

	public function name(): string {
		return $this->generic()->name();
	}

	public function description(): string {
		return match ( $this->slug ) {
			'yoast-seo' => __( 'SEO data on any post, page or product its owner can edit, including drafts, and on recent versions site-wide search settings. The plugin\'s own role rules still apply.', 'counterhand-mcp-for-woocommerce' ),
			'rank-math' => __( 'SEO data on any post, page or product its owner can edit, including drafts, and Rank Math\'s site-wide settings. The plugin\'s own role rules still apply.', 'counterhand-mcp-for-woocommerce' ),
			'wpforms'   => __( 'Your forms. With WPForms Pro this includes submissions, which are personal data. Form editing also needs WPForms\' own write access, under WPForms » Tools » AI MCP.', 'counterhand-mcp-for-woocommerce' ),
			default     => $this->generic()->description(),
		};
	}

	public function read_description(): string {
		return match ( $this->slug ) {
			'yoast-seo' => __( 'See titles, permalinks, status and SEO fields of any post, page or product the approving user can edit, including drafts.', 'counterhand-mcp-for-woocommerce' ),
			'rank-math' => __( 'See SEO data, redirections and 404 logs, and the same details of any post, page or product the approving user can edit, including drafts.', 'counterhand-mcp-for-woocommerce' ),
			'wpforms'   => __( 'See your forms. With WPForms Pro, also see submissions, including names, email addresses and everything people wrote.', 'counterhand-mcp-for-woocommerce' ),
			default     => $this->generic()->read_description(),
		};
	}

	public function write_description(): string {
		return match ( $this->slug ) {
			'yoast-seo' => __( 'Change SEO titles, noindex and canonical URLs, and on recent versions switch off sitemaps, schema and social tags or rewrite who the site says it is. A noindex removes a page from search results. Every change needs explicit confirmation.', 'counterhand-mcp-for-woocommerce' ),
			'rank-math' => __( 'Change site-wide SEO settings, including noindex for the whole site and which modules are on, and apply automatic fixes to core settings. Every change needs explicit confirmation.', 'counterhand-mcp-for-woocommerce' ),
			default     => $this->generic()->write_description(),
		};
	}

	private function generic(): GenericIntegrationCopy {
		return new GenericIntegrationCopy( $this->slug, self::NAMES[ $this->slug ] ?? null );
	}
}
