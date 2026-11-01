<?php

declare( strict_types=1 );

namespace Counterhand\Features\CostOfGoods;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Written by hand because the plugin adds costs to order line items and nothing to
 * products, and wc/v3 keeps the cost's meta key private. So no REST permission runs
 * behind this, and execute() asks WooCommerce's own check per item.
 */
final readonly class CostOfGoodsTool implements ToolInterface {

	public function __construct(
		private CostOperation $operation,
		private CostLedgerInterface $ledger,
		private IntegrationGroup $group,
	) {}

	public function name(): string {
		return $this->operation->value;
	}

	public function description(): string {
		return match ( $this->operation ) {
			CostOperation::GetProduct => 'Read what a product or variation costs the store, next to its price. cost is null when none is set. A variable product answers with its default cost, if it has one; read each variation for what it really costs.',
			CostOperation::SetProduct => 'Set what a product or variation costs the store, in every language it is translated into. Its price does not change, orders already placed keep the cost they were placed with, and a variable product is refused: set each variation.',
			CostOperation::GetOrder   => 'Read what one order earned: its lines\' totals and costs as recorded when it was placed, less refunds. Profit excludes tax, shipping and fees. profit is null, and profit_not_stated says why, when it cannot be right: a line with no cost, an unpaid order, another currency, or a refund of an amount rather than of items. Cost of Goods may record costs again when an order is saved through the REST API, if a line had none. No customer details are included.',
		};
	}

	public function input_schema(): array {
		$properties = [
			'id' => [
				'type'    => 'integer',
				'minimum' => 1,
			],
		];

		if ( CostOperation::SetProduct === $this->operation ) {
			$properties['cost'] = [
				'type'        => 'string',
				'description' => 'The cost as a decimal, such as 7.50.',
			];
		}

		return [
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => array_keys( $properties ),
			'additionalProperties' => false,
		];
	}

	public function required_scope(): ScopeInterface {
		return $this->is_read_only() ? $this->group->read_scope() : ( $this->group->write_scope() ?? $this->group->read_scope() );
	}

	public function group(): IntegrationGroup {
		return $this->group;
	}

	public function is_available(): bool {
		return $this->ledger->is_installed() && ( $this->is_read_only() ? $this->ledger->user_can_read_costs() : $this->ledger->user_can_edit_costs() );
	}

	public function requires_confirmation(): bool {
		return false;
	}

	public function is_read_only(): bool {
		return CostOperation::SetProduct !== $this->operation;
	}

	public function is_destructive(): bool {
		return false;
	}

	public function is_idempotent(): bool {
		return true;
	}

	public function is_open_world(): bool {
		return false;
	}

	public function execute( array $arguments ): array {
		$id = (int) ( $arguments['id'] ?? 0 );

		return match ( $this->operation ) {
			CostOperation::GetProduct => $this->read_product( $id ),
			CostOperation::SetProduct => $this->write_product( $id, (string) ( $arguments['cost'] ?? '' ) ),
			CostOperation::GetOrder   => $this->read_order( $id ),
		};
	}

	/** @return array<string, mixed> */
	private function read_product( int $id ): array {
		$product = $this->ledger->user_can_read_product( $id ) ? $this->ledger->product( $id ) : null;

		// One answer for both, so a caller cannot probe which ids exist.
		if ( null === $product ) {
			throw $this->not_found( 'product', $id );
		}

		return [
			'id'    => $id,
			'name'  => $product->name,
			'price' => $product->price,
			'cost'  => $product->cost,
		];
	}

	/** @return array<string, mixed> */
	private function write_product( int $id, string $cost ): array {
		if ( ! is_numeric( $cost ) || (float) $cost < 0 ) {
			throw new ToolCallException( 'cost must be a decimal of zero or more.' );
		}

		$product = $this->ledger->user_can_edit_product( $id ) ? $this->ledger->product( $id ) : null;

		if ( null === $product ) {
			throw $this->not_found( 'product', $id );
		}

		if ( 'variable' === $product->type ) {
			throw new ToolCallException( 'Set the cost on each variation. A variable product\'s default cost can only be set in the admin, which also passes it down to its variations.' );
		}

		$this->ledger->set_cost( $id, $cost );

		return $this->read_product( $id );
	}

	/** @return array<string, mixed> */
	private function read_order( int $id ): array {
		$order = $this->ledger->user_can_read_order( $id ) ? $this->ledger->order( $id ) : null;

		if ( null === $order ) {
			throw $this->not_found( 'order', $id );
		}

		$why_not = $order->why_no_profit();

		return [
			'id'                => $id,
			'status'            => $order->status,
			'total'             => $order->total,
			'currency'          => $order->currency,
			'lines'             => array_map( static fn ( CostedLine $line ): array => $line->to_array(), $order->lines ),
			'refunded_revenue'  => $order->refunded_revenue,
			'refunded_cost'     => $order->refunded_cost,
			'revenue'           => round( $order->revenue(), 2 ),
			'total_cost'        => round( $order->cost(), 2 ),
			// Line totals, because the order's total also holds tax and shipping, which are not earnings.
			'profit'            => null === $why_not ? round( $order->revenue() - $order->cost(), 2 ) : null,
			'profit_not_stated' => $why_not,
		];
	}

	private function not_found( string $noun, int $id ): ToolCallException {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
		return new ToolCallException( sprintf( 'No %s %d was found.', $noun, $id ) );
	}
}
