<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Refuses the system-status tools whose effects cannot be undone.
 *
 * WooCommerce's maintenance endpoint runs everything behind one capability,
 * manage_woocommerce — which every token owner of this plugin already holds, so
 * WooCommerce's own gate draws no distinction here at all. Yet the tools behind
 * it range from clearing a transient cache to deleting the orders table. This
 * is where that distinction gets made.
 *
 * It is our policy rather than WooCommerce logic, which is why encoding it is
 * not the reimplementation the rest of this plugin avoids: there is nothing
 * upstream to defer to.
 *
 * The list is what a store cannot recover from without a backup. Anything that
 * only costs time to rebuild — regenerating thumbnails, recounting terms,
 * clearing caches — is allowed, still behind an explicit confirmation.
 *
 * Verified against a WooCommerce 10.9.4 store, where the endpoint offered 27
 * tools including several a plugin had added. That is the reason this denies by
 * id rather than trying to enumerate what is safe: the safe set is whatever
 * happens to be installed, and a new plugin's destructive tool must not become
 * callable simply because nobody added it here.
 */
final readonly class SystemToolPolicy implements ArgumentPolicy {

	/**
	 * Irreversible without a database restore.
	 *
	 * - reset_roles rewrites every user's capabilities, and a store with
	 *   customised roles loses them.
	 * - delete_taxes drops every tax rate the store charges.
	 * - db_update_routine migrates the schema; there is no way back.
	 * - the HPOS and order-table entries delete order data outright.
	 * - install_pages writes new published pages onto the storefront.
	 */
	private const IRREVERSIBLE = [
		'reset_roles',
		'delete_taxes',
		'db_update_routine',
		'delete_custom_orders_table',
		'hpos_legacy_cleanup',
		'install_pages',
		'regenerate_product_lookup_tables',
		'regenerate_product_attributes_lookup_table',
		'recreate_order_address_fts_index',
		'delete_inbox_notification',
	];

	public function __construct( private string $argument = 'id' ) {}

	public function verdict( array $arguments ): Verdict {
		$id = (string) ( $arguments[ $this->argument ] ?? '' );

		if ( '' === $id ) {
			return Verdict::allow();
		}

		/**
		 * Filters whether one system-status tool may be run through this API.
		 *
		 * Both directions are useful: a store can allow one of the entries above
		 * after deciding it is safe there, or deny a maintenance tool its own
		 * plugins registered.
		 *
		 * @param bool   $denied Whether the shipped list refuses it.
		 * @param string $id     The system tool id.
		 */
		$denied = (bool) apply_filters(
			'counterhand_system_tool_denied',
			in_array( $id, self::IRREVERSIBLE, true ),
			$id
		);

		if ( ! $denied ) {
			return Verdict::allow();
		}

		return Verdict::deny(
			sprintf(
				'"%s" changes the store in a way that cannot be undone from here, so it is not available through this API. It can still be run by a person in WooCommerce > Status > Tools, where the consequences are spelled out and a backup can be taken first.',
				$id
			)
		);
	}
}
