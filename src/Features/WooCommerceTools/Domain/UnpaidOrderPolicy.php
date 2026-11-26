<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Lines change only on an order still waiting for its payment: a paid one would
 * then disagree with what the gateway captured and with the invoice it was sent.
 */
final readonly class UnpaidOrderPolicy implements ArgumentPolicy {

	public function verdict( array $arguments ): Verdict {
		$id    = $arguments['id'] ?? null;
		$order = is_numeric( $id ) ? wc_get_order( (int) $id ) : null;

		if ( ! $order instanceof \WC_Order ) {
			return Verdict::deny( 'id must be an existing order.' );
		}

		if ( ! $order->needs_payment() ) {
			return Verdict::deny( sprintf( 'Order %d is not waiting for payment (status %s), so its lines and addresses cannot be changed here. Tell the person; a paid order is changed with a refund or a new order.', (int) $id, $order->get_status() ) );
		}

		return Verdict::allow();
	}
}
