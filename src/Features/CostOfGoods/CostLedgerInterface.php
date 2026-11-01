<?php

declare( strict_types=1 );

namespace Counterhand\Features\CostOfGoods;

defined( 'ABSPATH' ) || exit;

interface CostLedgerInterface {

	public function is_installed(): bool;

	public function user_can_read_costs(): bool;

	public function user_can_edit_costs(): bool;

	public function user_can_read_product( int $id ): bool;

	public function user_can_edit_product( int $id ): bool;

	public function user_can_read_order( int $id ): bool;

	/** Null for an id that is no product. */
	public function product( int $id ): ?CostedProduct;

	public function set_cost( int $id, string $cost ): void;

	/** Null for an id that is no order. */
	public function order( int $id ): ?CostedOrder;
}
