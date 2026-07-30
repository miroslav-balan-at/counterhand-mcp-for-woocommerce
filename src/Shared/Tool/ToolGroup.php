<?php

declare( strict_types=1 );

namespace Counterhand\Shared\Tool;

use Counterhand\Features\Tokens\Domain\ApiScope;

defined( 'ABSPATH' ) || exit;

/**
 * A family of tools the store owner switches on or off as a unit.
 *
 * The value is load-bearing three times over: it prefixes every ApiScope, it
 * prefixes every ctrh_settings option key, and it is what an existing install
 * has already persisted. Renaming a case orphans stored settings.
 *
 * Scopes and option keys are derived from the value rather than restated, so a
 * new group cannot drift out of step with its own scope.
 */
enum ToolGroup: string {
	case Products   = 'products';
	case Orders     = 'orders';
	case Customers  = 'customers';
	case Reports    = 'reports';
	case Coupons    = 'coupons';
	case Taxonomy   = 'taxonomy';
	case Variations = 'variations';
	case Reviews    = 'reviews';
	case Refunds    = 'refunds';
	case Shipping   = 'shipping';
	case Taxes      = 'taxes';
	case Data       = 'data';
	case Gateways   = 'gateways';
	case Settings   = 'settings';
	case Content    = 'content';
	case System     = 'system';

	/*
	 * The three methods below are deliberately exhaustive, with no default arm.
	 *
	 * A default would be a fallback nobody wants to ship: an untranslated slug
	 * for a label, empty prose for a description, or a section guessed on the
	 * group's behalf. Leaving them exhaustive means adding a case here fails
	 * `composer run analyse` with "Match expression does not handle remaining
	 * value" until the group states its own wording and its own section — which
	 * is the decision we want made deliberately, not defaulted.
	 */

	public function label(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Products  => __( 'Products', 'counterhand-mcp-for-woocommerce' ),
			self::Orders    => __( 'Orders', 'counterhand-mcp-for-woocommerce' ),
			self::Customers => __( 'Customers', 'counterhand-mcp-for-woocommerce' ),
			self::Reports   => __( 'Reports', 'counterhand-mcp-for-woocommerce' ),
			self::Coupons    => __( 'Coupons', 'counterhand-mcp-for-woocommerce' ),
			self::Taxonomy   => __( 'Categories & tags', 'counterhand-mcp-for-woocommerce' ),
			self::Variations => __( 'Variations', 'counterhand-mcp-for-woocommerce' ),
			self::Reviews    => __( 'Reviews', 'counterhand-mcp-for-woocommerce' ),
			self::Refunds    => __( 'Refunds', 'counterhand-mcp-for-woocommerce' ),
			self::Shipping   => __( 'Shipping', 'counterhand-mcp-for-woocommerce' ),
			self::Taxes      => __( 'Taxes', 'counterhand-mcp-for-woocommerce' ),
			self::Data       => __( 'Reference data', 'counterhand-mcp-for-woocommerce' ),
			self::Gateways   => __( 'Payment gateways', 'counterhand-mcp-for-woocommerce' ),
			self::Settings   => __( 'Store settings', 'counterhand-mcp-for-woocommerce' ),
			self::Content    => __( 'Posts & pages', 'counterhand-mcp-for-woocommerce' ),
			self::System     => __( 'System & maintenance', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	/** One line on the settings row explaining what switching the group on exposes. */
	public function description(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Products  => __( 'Names, prices, stock, categories and images.', 'counterhand-mcp-for-woocommerce' ),
			self::Orders    => __( 'Orders, totals, line items and customer addresses.', 'counterhand-mcp-for-woocommerce' ),
			self::Customers => __( 'Registered customers and their addresses. Guest buyers appear under Orders.', 'counterhand-mcp-for-woocommerce' ),
			self::Reports   => __( 'Sales totals, best sellers and store statistics.', 'counterhand-mcp-for-woocommerce' ),
			self::Coupons    => __( 'Discount codes, their amounts, limits and expiry dates.', 'counterhand-mcp-for-woocommerce' ),
			self::Taxonomy   => __( 'How the catalog is organised: categories, tags, brands, attributes and shipping classes.', 'counterhand-mcp-for-woocommerce' ),
			self::Variations => __( 'The size, colour and other variants of a variable product, each with its own price and stock.', 'counterhand-mcp-for-woocommerce' ),
			self::Reviews    => __( 'Customer reviews and ratings, including reviewer names and email addresses.', 'counterhand-mcp-for-woocommerce' ),
			self::Refunds    => __( 'Refunds against orders. Creating one moves money back to the customer.', 'counterhand-mcp-for-woocommerce' ),
			self::Shipping   => __( 'Shipping zones, the regions they cover and the methods offered in each.', 'counterhand-mcp-for-woocommerce' ),
			self::Taxes      => __( 'Tax rates by country and region, and the tax classes products are assigned to.', 'counterhand-mcp-for-woocommerce' ),
			self::Data       => __( 'WooCommerce\'s own reference lists of countries, regions and currencies.', 'counterhand-mcp-for-woocommerce' ),
			self::Gateways   => __( 'Which payment methods exist and whether each is enabled. Credentials are never returned.', 'counterhand-mcp-for-woocommerce' ),
			self::Settings   => __( 'Store configuration values, including currency, tax behaviour and checkout options.', 'counterhand-mcp-for-woocommerce' ),
			self::Content    => __( 'WordPress blog posts and pages. Products and orders are not affected.', 'counterhand-mcp-for-woocommerce' ),
			self::System     => __( 'Environment report and WooCommerce\'s maintenance tools. The most dangerous group here.', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	/**
	 * The group's name as it reads mid-sentence, e.g. "Read products".
	 *
	 * Separate from label() because a heading and a sentence do not agree on
	 * case in English and disagree far more elsewhere — German capitalises the
	 * noun either way, Spanish would want an article. Translators need both
	 * strings to get either right, so this is not label() lowercased.
	 */
	public function noun(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Products  => __( 'products', 'counterhand-mcp-for-woocommerce' ),
			self::Orders    => __( 'orders', 'counterhand-mcp-for-woocommerce' ),
			self::Customers => __( 'customers', 'counterhand-mcp-for-woocommerce' ),
			self::Reports   => __( 'reports', 'counterhand-mcp-for-woocommerce' ),
			self::Coupons    => __( 'coupons', 'counterhand-mcp-for-woocommerce' ),
			self::Taxonomy   => __( 'categories and tags', 'counterhand-mcp-for-woocommerce' ),
			self::Variations => __( 'variations', 'counterhand-mcp-for-woocommerce' ),
			self::Reviews    => __( 'reviews', 'counterhand-mcp-for-woocommerce' ),
			self::Refunds    => __( 'refunds', 'counterhand-mcp-for-woocommerce' ),
			self::Shipping   => __( 'shipping', 'counterhand-mcp-for-woocommerce' ),
			self::Taxes      => __( 'taxes', 'counterhand-mcp-for-woocommerce' ),
			self::Data       => __( 'reference data', 'counterhand-mcp-for-woocommerce' ),
			self::Gateways   => __( 'payment gateways', 'counterhand-mcp-for-woocommerce' ),
			self::Settings   => __( 'store settings', 'counterhand-mcp-for-woocommerce' ),
			self::Content    => __( 'posts and pages', 'counterhand-mcp-for-woocommerce' ),
			self::System     => __( 'system tools', 'counterhand-mcp-for-woocommerce' ),
		};
	}

	public function section(): ToolSection {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			// A coupon is a pricing rule over the catalog, and it is the catalog
			// heading a shop manager looks under to find one.
			self::Products, self::Coupons, self::Taxonomy, self::Variations, self::Reviews => ToolSection::Catalog,
			self::Orders, self::Customers, self::Refunds => ToolSection::Sales,
			self::Reports                                => ToolSection::Insights,
			self::Content                                => ToolSection::Content,
			self::Shipping, self::Taxes, self::Data      => ToolSection::Store,
			// Both can change how the store charges money, so both stay behind
			// the collapsed heading and are never pre-ticked on consent.
			self::Gateways, self::Settings, self::System => ToolSection::Advanced,
		};
	}

	public function read_scope(): ApiScope {
		return ApiScope::from( $this->value . ':read' ); // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	/** Null for groups WooCommerce exposes read-only, such as reports. */
	public function write_scope(): ?ApiScope {
		return ApiScope::tryFrom( $this->value . ':write' ); // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	public function has_write(): bool {
		return null !== $this->write_scope(); // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	public function read_option_key(): string {
		return $this->value . '_read'; // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	public function write_option_key(): string {
		return $this->value . '_write'; // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	/**
	 * Whether a fresh install exposes this group's reads.
	 *
	 * Writes always ship off, so this only governs the read axis. New groups
	 * arrive disabled: an upgrade must never widen what a store exposes.
	 */
	public function enabled_by_default(): bool {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Products, self::Orders, self::Reports => true,
			default => false,
		};
	}

	/**
	 * Whether a call to this group is recorded even with the action log off.
	 *
	 * The log is a convenience for routine traffic and a store may reasonably
	 * turn it off. It is not a convenience for a maintenance routine run against
	 * the live database, or for a change to how the store charges money — a
	 * store owner who finds their tax rates gone deserves a record of what asked
	 * for it, whatever their logging preference was.
	 */
	public function is_always_logged(): bool {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::System, self::Settings, self::Gateways => true,
			default => false,
		};
	}

	/**
	 * Whether the admin chat starts able to reach this group.
	 *
	 * Deliberately not enabled_by_default(): that answers what the store exposes
	 * to outside assistants, this answers how much surface one chat request puts
	 * in front of a model. The two disagree already — Customers is closed to the
	 * world by default but useful in an admin's own chat — and they disagree more
	 * as the catalogue grows, because a chat pays for every enabled group on
	 * every message while the connector pays only for what a client calls.
	 *
	 * A `default` arm rather than an exhaustive match: new groups stay out of the
	 * chat until someone decides they earn their place in the prompt.
	 */
	public function in_chat_by_default(): bool {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Products, self::Orders, self::Customers, self::Reports => true,
			default => false,
		};
	}
}
