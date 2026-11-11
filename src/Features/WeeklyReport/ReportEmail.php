<?php

declare( strict_types=1 );

namespace Counterhand\Features\WeeklyReport;

use Counterhand\Features\Settings\AdminScreen;

defined( 'ABSPATH' ) || exit;

/**
 * Built as one line of markup, because WooCommerce's wrapper runs wpautop over the body
 * and would put paragraphs between table rows at every line break.
 */
final readonly class ReportEmail {

	public function __construct(
		public string $subject,
		public string $heading,
		public string $body,
	) {}

	public static function of( WeeklyFigures $figures, ?string $summary ): self {
		$store = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		return new self(
			subject: sprintf(
				/* translators: 1: store name, 2: the week, e.g. "3 November 2026 to 9 November 2026". */
				__( 'Weekly report for %1$s: %2$s', 'counterhand-mcp-for-woocommerce' ),
				$store,
				$figures->week->label()
			),
			heading: __( 'Your week in the store', 'counterhand-mcp-for-woocommerce' ),
			body: implode(
				'',
				[
					self::summary( $summary ),
					self::sales( $figures ),
					self::top_sellers( $figures ),
					self::waiting( $figures ),
					self::footer(),
				]
			),
		);
	}

	private static function summary( ?string $summary ): string {
		if ( null === $summary ) {
			return '';
		}

		return sprintf(
			'<p>%s</p><p><em>%s</em></p>',
			esc_html( $summary ),
			esc_html__( 'Written by AI from the figures below. Check it against them.', 'counterhand-mcp-for-woocommerce' )
		);
	}

	private static function sales( WeeklyFigures $figures ): string {
		$rows = [
			[ __( 'Total sales', 'counterhand-mcp-for-woocommerce' ), self::money( $figures->sales->total_sales ), self::money( $figures->previous->total_sales ) ],
			[ __( 'Net sales', 'counterhand-mcp-for-woocommerce' ), self::money( $figures->sales->net_sales ), self::money( $figures->previous->net_sales ) ],
			[ __( 'Orders', 'counterhand-mcp-for-woocommerce' ), number_format_i18n( $figures->sales->orders ), number_format_i18n( $figures->previous->orders ) ],
			[ __( 'Items sold', 'counterhand-mcp-for-woocommerce' ), number_format_i18n( $figures->sales->items ), number_format_i18n( $figures->previous->items ) ],
			[ __( 'Refunds', 'counterhand-mcp-for-woocommerce' ), self::money( $figures->sales->refunds ), self::money( $figures->previous->refunds ) ],
		];

		return sprintf(
			'<h2>%s</h2><table cellspacing="0" cellpadding="6" border="1" style="width:100%%;border-collapse:collapse"><thead><tr><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th></tr></thead><tbody>%s</tbody></table>',
			esc_html__( 'Sales', 'counterhand-mcp-for-woocommerce' ),
			esc_html__( 'Figure', 'counterhand-mcp-for-woocommerce' ),
			esc_html__( 'This week', 'counterhand-mcp-for-woocommerce' ),
			esc_html__( 'Week before', 'counterhand-mcp-for-woocommerce' ),
			implode(
				'',
				array_map(
					static fn ( array $row ): string => sprintf( '<tr><th scope="row">%s</th><td>%s</td><td>%s</td></tr>', esc_html( $row[0] ), wp_kses_post( $row[1] ), wp_kses_post( $row[2] ) ),
					$rows
				)
			)
		);
	}

	private static function top_sellers( WeeklyFigures $figures ): string {
		$heading = sprintf( '<h2>%s</h2>', esc_html__( 'Best sellers', 'counterhand-mcp-for-woocommerce' ) );

		if ( [] === $figures->top_sellers ) {
			return $heading . sprintf( '<p>%s</p>', esc_html__( 'Nothing was sold this week.', 'counterhand-mcp-for-woocommerce' ) );
		}

		return $heading . '<ol>' . implode(
			'',
			array_map(
				static fn ( TopSeller $seller ): string => sprintf(
					'<li>%s</li>',
					esc_html(
						sprintf(
							/* translators: 1: product name, 2: number sold. */
							__( '%1$s: %2$s sold', 'counterhand-mcp-for-woocommerce' ),
							$seller->name,
							number_format_i18n( $seller->quantity )
						)
					)
				),
				$figures->top_sellers
			)
		) . '</ol>';
	}

	private static function waiting( WeeklyFigures $figures ): string {
		return sprintf(
			'<h2>%s</h2><p>%s</p><p><a href="%s">%s</a></p>',
			esc_html__( 'Waiting now', 'counterhand-mcp-for-woocommerce' ),
			esc_html(
				sprintf(
					/* translators: 1: number of orders processing, 2: number of orders on hold. */
					__( '%1$s orders processing and %2$s on hold.', 'counterhand-mcp-for-woocommerce' ),
					number_format_i18n( $figures->processing ),
					number_format_i18n( $figures->on_hold )
				)
			),
			esc_url( self::orders_url() ),
			esc_html__( 'Open the orders', 'counterhand-mcp-for-woocommerce' )
		);
	}

	private static function footer(): string {
		return sprintf(
			'<p><small>%s <a href="%s">%s</a></small></p>',
			esc_html__( 'You get this because you asked for a weekly report in Counterhand MCP.', 'counterhand-mcp-for-woocommerce' ),
			esc_url( AdminScreen::Overview->url() ),
			esc_html__( 'Stop it on the Dashboard.', 'counterhand-mcp-for-woocommerce' )
		);
	}

	private static function orders_url(): string {
		$uses_order_tables = class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		return admin_url( $uses_order_tables ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order' );
	}

	private static function money( float $amount ): string {
		return function_exists( 'wc_price' ) ? wc_price( $amount ) : number_format_i18n( $amount, 2 );
	}
}
