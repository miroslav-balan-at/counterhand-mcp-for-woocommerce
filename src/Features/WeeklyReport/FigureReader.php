<?php

declare( strict_types=1 );

namespace Counterhand\Features\WeeklyReport;

use Counterhand\Features\McpServer\DispatchOutcome;
use Counterhand\Features\McpServer\ToolDispatcherInterface;
use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the week through the report tools, as the person the report is for, so a store
 * that keeps reports switched off sends nothing and each figure is one WooCommerce states.
 */
final readonly class FigureReader {

	private const TOP_SELLERS = 5;

	public function __construct( private ToolDispatcherInterface $tools ) {}

	public function can_read( int $user_id ): bool {
		return [] !== array_filter(
			$this->tools->visible_for( ReportAgent::for_user( $user_id ) ),
			static fn ( ToolInterface $tool ): bool => 'get_sales_report' === $tool->name()
		);
	}

	/** @throws ToolCallException When a report cannot be read. */
	public function read( int $user_id, ReportWeek $week ): WeeklyFigures {
		$agent = ReportAgent::for_user( $user_id );

		$sales    = $this->rows( 'get_sales_report', $week->range(), 'sales', $agent );
		$previous = $this->rows( 'get_sales_report', $week->previous()->range(), 'sales', $agent );
		$sellers  = $this->rows( 'get_top_sellers', $week->range(), 'top_sellers', $agent );
		$statuses = array_column( $this->rows( 'get_order_totals', [], 'order_totals', $agent ), 'total', 'slug' );

		return new WeeklyFigures(
			week: $week,
			sales: SalesFigures::from_report( $sales[0] ?? [] ),
			previous: SalesFigures::from_report( $previous[0] ?? [] ),
			top_sellers: array_map(
				static fn ( array $row ): TopSeller => new TopSeller( (string) ( $row['name'] ?? '' ), (int) ( $row['quantity'] ?? 0 ) ),
				array_slice( $sellers, 0, self::TOP_SELLERS )
			),
			processing: (int) ( $statuses['processing'] ?? 0 ),
			on_hold: (int) ( $statuses['on-hold'] ?? 0 ),
		);
	}

	/**
	 * @param  array<string, mixed> $arguments
	 * @return list<array<string, mixed>>
	 * @throws ToolCallException When the report cannot be read.
	 */
	private function rows( string $tool, array $arguments, string $key, AuthenticatedAgent $agent ): array {
		$outcome = $this->tools->dispatch( $tool, $arguments, $agent );

		if ( $outcome->is_error() ) {
			throw new ToolCallException( $outcome->message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- logged, never printed.
		}

		return self::listed( $outcome, $key );
	}

	/** @return list<array<string, mixed>> */
	private static function listed( DispatchOutcome $outcome, string $key ): array {
		return array_values( array_filter( (array) ( $outcome->data[ $key ] ?? [] ), 'is_array' ) );
	}
}
