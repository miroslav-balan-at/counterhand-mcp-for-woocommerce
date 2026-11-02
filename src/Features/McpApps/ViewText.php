<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpApps;

defined( 'ABSPATH' ) || exit;

/**
 * What the views say. People read these, so unlike tool descriptions they are translated.
 */
final readonly class ViewText {

	/** @return array<string, string> */
	public static function all(): array {
		return [
			'failed'       => __( 'This could not be shown.', 'counterhand-mcp-for-woocommerce' ),
			'salesTitle'   => __( 'Sales', 'counterhand-mcp-for-woocommerce' ),
			'totalSales'   => __( 'Total sales', 'counterhand-mcp-for-woocommerce' ),
			'netSales'     => __( 'Net sales', 'counterhand-mcp-for-woocommerce' ),
			'orders'       => __( 'Orders', 'counterhand-mcp-for-woocommerce' ),
			'itemsSold'    => __( 'Items sold', 'counterhand-mcp-for-woocommerce' ),
			'refunds'      => __( 'Refunds', 'counterhand-mcp-for-woocommerce' ),
			'orderTitle'   => __( 'Order', 'counterhand-mcp-for-woocommerce' ),
			'status'       => __( 'Status', 'counterhand-mcp-for-woocommerce' ),
			'total'        => __( 'Total', 'counterhand-mcp-for-woocommerce' ),
			'customer'     => __( 'Customer', 'counterhand-mcp-for-woocommerce' ),
			'customerNote' => __( 'Customer note', 'counterhand-mcp-for-woocommerce' ),
			'previewTitle' => __( 'Proposed change, not saved', 'counterhand-mcp-for-woocommerce' ),
			'savedTitle'   => __( 'Change saved', 'counterhand-mcp-for-woocommerce' ),
			'field'        => __( 'Field', 'counterhand-mcp-for-woocommerce' ),
			'before'       => __( 'Before', 'counterhand-mcp-for-woocommerce' ),
			'after'        => __( 'After', 'counterhand-mcp-for-woocommerce' ),
			'empty'        => __( 'Empty', 'counterhand-mcp-for-woocommerce' ),
		];
	}
}
