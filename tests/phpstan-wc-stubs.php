<?php
/**
 * Minimal WooCommerce symbol declarations for PHPStan.
 * Only the functions this plugin calls — not a full stub set.
 */

declare( strict_types=1 );

/** @return array<string, string> */
function wc_get_order_statuses(): array {}

function wc_orders_count( string $status ): int {}

function get_woocommerce_currency(): string {}

/** @return object{countries: object} */
function WC(): object {}

function wc_get_logger(): object {}
