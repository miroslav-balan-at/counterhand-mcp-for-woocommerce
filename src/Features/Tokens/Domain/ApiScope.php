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
			self::ProductsRead  => __( 'Products — read', 'agentgate-mcp-for-woocommerce' ),
			self::ProductsWrite => __( 'Products — write (create, update, delete)', 'agentgate-mcp-for-woocommerce' ),
			self::OrdersRead    => __( 'Orders — read', 'agentgate-mcp-for-woocommerce' ),
			self::OrdersWrite   => __( 'Orders — write (status, notes)', 'agentgate-mcp-for-woocommerce' ),
			self::CustomersRead => __( 'Customers — read', 'agentgate-mcp-for-woocommerce' ),
			self::ReportsRead   => __( 'Reports — read', 'agentgate-mcp-for-woocommerce' ),
		};
	}
}
