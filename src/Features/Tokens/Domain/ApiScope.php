<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * The scope catalog. Write never implies read — both are granted explicitly.
 */
enum ApiScope: string {
	case ProductsRead  = 'products:read';
	case ProductsWrite = 'products:write';
	case OrdersRead    = 'orders:read';
	case OrdersWrite   = 'orders:write';
	case CustomersRead = 'customers:read';
	case ReportsRead   = 'reports:read';
	case CouponsRead   = 'coupons:read';
	case CouponsWrite  = 'coupons:write';

	/*
	 * A group whose write axis WooCommerce does not offer simply has no :write
	 * case, and ToolGroup::has_write() reads that back off this enum — so the
	 * omissions below are load-bearing, not oversights. Reports and Data are
	 * read-only aggregates. Gateways and Settings are writable in WooCommerce
	 * but deliberately read-only here until the confirmation and denylist work
	 * lands; adding the case is what turns them on.
	 */

	case TaxonomyRead    = 'taxonomy:read';
	case TaxonomyWrite   = 'taxonomy:write';
	case VariationsRead  = 'variations:read';
	case VariationsWrite = 'variations:write';
	case ReviewsRead     = 'reviews:read';
	case ReviewsWrite    = 'reviews:write';
	case RefundsRead     = 'refunds:read';
	case RefundsWrite    = 'refunds:write';
	case ShippingRead    = 'shipping:read';
	case ShippingWrite   = 'shipping:write';
	case TaxesRead       = 'taxes:read';
	case TaxesWrite      = 'taxes:write';
	case DataRead        = 'data:read';
	case GatewaysRead    = 'gateways:read';
	case GatewaysWrite   = 'gateways:write';
	case SettingsRead    = 'settings:read';
	case SettingsWrite   = 'settings:write';
	case ContentRead     = 'content:read';
	case ContentWrite    = 'content:write';
	case SystemRead      = 'system:read';
	case SystemWrite     = 'system:write';

	/** @return list<string> */
	public static function values(): array {
		return array_map( static fn ( self $scope ): string => $scope->value, self::cases() );
	}

	/**
	 * What a client that named no scopes at all gets offered on the consent screen.
	 *
	 * OAuth lets the authorization server pick a default when `scope` is absent,
	 * and some MCP clients do omit it. Offering everything would mean a client
	 * that asked for nothing is pre-checked for every write this plugin has —
	 * a default that grows more dangerous with each release rather than staying
	 * put. So the default is derived and doubly narrowed: read axis only, and
	 * only from sections that are not Advanced.
	 *
	 * The admin still sees and can uncheck every line, and the store's own group
	 * toggles gate anything granted here — this only decides what is offered.
	 *
	 * @return list<self>
	 */
	public static function conservative_default(): array {
		$groups = array_merge(
			...array_map(
				static fn ( ToolSection $section ): array => $section->is_advanced() ? [] : $section->groups(),
				ToolSection::cases()
			)
		);

		return array_map( static fn ( ToolGroup $group ): self => $group->read_scope(), $groups );
	}

	public function is_write(): bool {
		return str_ends_with( $this->value, ':write' ); // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	/** The group this scope gates. Every scope value is "<group>:<axis>". */
	public function group(): ToolGroup {
		[ $prefix ] = explode( ':', $this->value ); // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.

		return ToolGroup::from( $prefix );
	}

	/**
	 * Consent-screen wording. Derived from the group by default — "Read
	 * products", "Manage coupons" — so the scopes whose phrasing really is
	 * mechanical do not each need hand-written text that only differs by a
	 * noun. Only scopes the mechanical phrasing gets *wrong* are curated.
	 *
	 * This is a lookup with a fallback rather than a match with a default arm:
	 * the fallback is the normal path, taken by five of the six scopes today,
	 * so it is live code rather than a branch kept alive by a suppression.
	 */
	public function label(): string {
		return self::curated_labels()[ $this->value ] // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			?? self::derived_label( $this->group(), $this->is_write() ); // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	/**
	 * Plain-language consequence of granting the scope, for the consent screen.
	 *
	 * Curated for every scope that exists today, because a consequence is the
	 * one thing worth spelling out before someone grants it. The group's own
	 * description is the floor for a scope added without that care.
	 */
	public function description(): string {
		return self::curated_descriptions()[ $this->value ] // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			?? $this->group()->description(); // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	/**
	 * Only where derivation would mislead: orders:write cannot create or delete
	 * an order, so "Manage orders" would promise more than it grants.
	 *
	 * @return array<string, string>
	 */
	private static function curated_labels(): array {
		return [
			self::OrdersWrite->value => __( 'Update orders', 'counterhand-mcp-for-woocommerce' ),
		];
	}

	/** @return array<string, string> */
	private static function curated_descriptions(): array {
		return [
			self::ProductsRead->value    => __( 'See product names, prices, stock and categories.', 'counterhand-mcp-for-woocommerce' ),
			self::ProductsWrite->value   => __( 'Create, edit and trash products. New products start as drafts.', 'counterhand-mcp-for-woocommerce' ),
			self::OrdersRead->value      => __( 'See orders, totals and customer addresses.', 'counterhand-mcp-for-woocommerce' ),
			self::OrdersWrite->value     => __( 'Change order status and add order notes. May send customer emails.', 'counterhand-mcp-for-woocommerce' ),
			self::CustomersRead->value   => __( 'See registered customers and their addresses.', 'counterhand-mcp-for-woocommerce' ),
			self::ReportsRead->value     => __( 'See sales totals, best sellers and store statistics.', 'counterhand-mcp-for-woocommerce' ),
			self::CouponsRead->value     => __( 'See discount codes, their amounts, limits and expiry dates.', 'counterhand-mcp-for-woocommerce' ),
			self::CouponsWrite->value    => __( 'Create, edit and trash discount codes. A new coupon can be redeemed at checkout immediately.', 'counterhand-mcp-for-woocommerce' ),

			// The consequence of each of these is money, personal data or the
			// shape of the storefront — none of which "Manage <noun>" conveys.
			self::RefundsWrite->value    => __( 'Issue refunds against orders. Depending on the gateway this returns money to the customer immediately and cannot be undone here.', 'counterhand-mcp-for-woocommerce' ),
			self::ReviewsRead->value     => __( 'See customer reviews, including reviewer names and email addresses.', 'counterhand-mcp-for-woocommerce' ),
			self::ReviewsWrite->value    => __( 'Publish, edit, hold and delete customer reviews. Approving a review makes it public on the storefront.', 'counterhand-mcp-for-woocommerce' ),
			self::TaxonomyWrite->value   => __( 'Create, rename and delete categories, tags, brands and attributes. Deleting one detaches it from every product using it.', 'counterhand-mcp-for-woocommerce' ),
			self::VariationsWrite->value => __( 'Create, edit and delete the variants of a variable product, including their prices and stock.', 'counterhand-mcp-for-woocommerce' ),
			self::TaxesWrite->value      => __( 'Add, edit and delete tax rates. This changes what customers are charged at checkout.', 'counterhand-mcp-for-woocommerce' ),
			self::ShippingWrite->value   => __( 'Add, edit and delete shipping zones and methods. This changes the delivery options and rates offered at checkout.', 'counterhand-mcp-for-woocommerce' ),
			self::GatewaysRead->value    => __( 'See which payment methods exist and whether each is enabled. API keys and secrets are never returned.', 'counterhand-mcp-for-woocommerce' ),
			self::SettingsRead->value    => __( 'Read store configuration such as currency, tax behaviour and checkout options. Values that look like keys, secrets or passwords are withheld.', 'counterhand-mcp-for-woocommerce' ),
			self::SettingsWrite->value   => __( 'Change store configuration, including currency and tax behaviour. Settings named like API keys, secrets or passwords stay off limits, and every change needs explicit confirmation.', 'counterhand-mcp-for-woocommerce' ),
			self::GatewaysWrite->value   => __( 'Enable, disable and reorder payment methods. This decides how customers can pay, and every change needs explicit confirmation.', 'counterhand-mcp-for-woocommerce' ),
			self::ContentRead->value     => __( 'See blog posts and pages, including drafts.', 'counterhand-mcp-for-woocommerce' ),
			self::ContentWrite->value    => __( 'Create, edit and trash blog posts and pages. New ones start as drafts.', 'counterhand-mcp-for-woocommerce' ),
			self::SystemRead->value      => __( 'See the environment report: versions, server settings and active plugins.', 'counterhand-mcp-for-woocommerce' ),
			self::SystemWrite->value     => __( 'Run WooCommerce\'s maintenance tools. The ones that cannot be undone are refused outright; the rest need explicit confirmation.', 'counterhand-mcp-for-woocommerce' ),
		];
	}

	private static function derived_label( ToolGroup $group, bool $is_write ): string {
		return $is_write
			/* translators: %s: tool group name mid-sentence, e.g. "products". */
			? sprintf( __( 'Manage %s', 'counterhand-mcp-for-woocommerce' ), $group->noun() )
			/* translators: %s: tool group name mid-sentence, e.g. "products". */
			: sprintf( __( 'Read %s', 'counterhand-mcp-for-woocommerce' ), $group->noun() );
	}
}
