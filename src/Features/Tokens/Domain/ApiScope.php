<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Tokens\Domain;

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

	/** @return list<string> */
	public static function values(): array {
		return array_map( static fn ( self $scope ): string => $scope->value, self::cases() );
	}

	public function is_write(): bool {
		return str_ends_with( $this->value, ':write' ); // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
	}

	public function label(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::ProductsRead  => __( 'Read products', 'agentgate-mcp-for-woocommerce' ),
			self::ProductsWrite => __( 'Manage products', 'agentgate-mcp-for-woocommerce' ),
			self::OrdersRead    => __( 'Read orders', 'agentgate-mcp-for-woocommerce' ),
			self::OrdersWrite   => __( 'Update orders', 'agentgate-mcp-for-woocommerce' ),
			self::CustomersRead => __( 'Read customers', 'agentgate-mcp-for-woocommerce' ),
			self::ReportsRead   => __( 'Read reports', 'agentgate-mcp-for-woocommerce' ),
		};
	}

	/** Plain-language consequence of granting the scope, for the consent screen. */
	public function description(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::ProductsRead  => __( 'See product names, prices, stock and categories.', 'agentgate-mcp-for-woocommerce' ),
			self::ProductsWrite => __( 'Create, edit and trash products. New products start as drafts.', 'agentgate-mcp-for-woocommerce' ),
			self::OrdersRead    => __( 'See orders, totals and customer addresses.', 'agentgate-mcp-for-woocommerce' ),
			self::OrdersWrite   => __( 'Change order status and add order notes. May send customer emails.', 'agentgate-mcp-for-woocommerce' ),
			self::CustomersRead => __( 'See registered customers and their addresses.', 'agentgate-mcp-for-woocommerce' ),
			self::ReportsRead   => __( 'See sales totals, best sellers and store statistics.', 'agentgate-mcp-for-woocommerce' ),
		};
	}
}
