<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Domain\TranslatableString;
use Counterhand\Features\WooCommerceTools\Domain\TranslatableStringPage;

defined( 'ABSPATH' ) || exit;

/**
 * WPML has an API to set a string's translation and none to list strings, so reads go
 * to its tables.
 *
 * Only contexts named here are reachable, because the rest holds what a token must not
 * change. A translated bank account in admin_texts_woocommerce_bacs_accounts would send
 * customers' payments elsewhere, WP Endpoints changes URLs, and an administrator can
 * register any option, so a plugin's secret can be a string too.
 */
final readonly class WpmlStrings implements TranslatableStringsInterface {

	private const WHOLE_CONTEXTS = [ 'WordPress', 'woocommerce', 'Widgets', 'block-widget', 'admin_texts_woocommerce_shipping' ];

	/** WooCommerce's block strings, one context per block. */
	private const CONTEXT_PREFIX = 'woocommerce/';

	private const EMAIL_TEXTS = [ 'subject', 'heading', 'additional_content', 'mail_content', 'footer_text', 'custom_message' ];

	/** Named here because WPML has no route to ask: the one exception to never hardcoding a capability. */
	private const CAPABILITIES = [ 'wpml_manage_string_translation', 'manage_translations' ];

	public function is_installed(): bool {
		return function_exists( 'icl_add_string_translation' ) && defined( 'ICL_TM_COMPLETE' );
	}

	public function user_can_manage(): bool {
		foreach ( self::CAPABILITIES as $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}
		}

		return false;
	}

	public function page( string $language, string $search, bool $only_missing, int $per_page, int $page ): TranslatableStringPage {
		global $wpdb;

		[ $where, $parameters ] = $this->reachable();

		if ( '' !== $search ) {
			$like         = '%' . $wpdb->esc_like( $search ) . '%';
			$where       .= ' AND ( s.value LIKE %s OR s.name LIKE %s )';
			$parameters[] = $like;
			$parameters[] = $like;
		}

		$where       .= ' AND s.language <> %s';
		$parameters[] = $language;

		if ( $only_missing ) {
			$where .= ' AND ( t.value IS NULL OR t.value = \'\' )';
		}

		$from = "{$wpdb->prefix}icl_strings s LEFT JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id AND t.language = %s";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- WPML's own tables, no API lists them, and $where holds only placeholders.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$from} WHERE {$where}", $language, ...$parameters ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT s.id, s.context, s.name, s.value, s.language, t.value AS translation FROM {$from} WHERE {$where} ORDER BY s.id LIMIT %d OFFSET %d", $language, ...[ ...$parameters, $per_page, ( $page - 1 ) * $per_page ] ) );
		// phpcs:enable

		return new TranslatableStringPage( array_map( [ self::class, 'from_row' ], (array) $rows ), $total );
	}

	public function find( int $id, string $language ): ?TranslatableString {
		global $wpdb;

		[ $where, $parameters ] = $this->reachable();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- as above.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT s.id, s.context, s.name, s.value, s.language, t.value AS translation FROM {$wpdb->prefix}icl_strings s LEFT JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id AND t.language = %s WHERE s.id = %d AND {$where}", $language, $id, ...$parameters ) );
		// phpcs:enable

		return is_object( $row ) ? self::from_row( $row ) : null;
	}

	public function is_active_language( string $language ): bool {
		return true === apply_filters( 'wpml_language_is_active', false, $language );
	}

	public function save( int $id, string $language, string $value ): bool {
		return false !== icl_add_string_translation( $id, $language, $value, ICL_TM_COMPLETE );
	}

	/**
	 * The email settings of WooCommerce's own emails, not every admin_texts_ context,
	 * which also holds payment gateways. The list comes from WooCommerce, so an email a
	 * plugin adds is reachable and a gateway never is. Within them only the words a
	 * customer reads: an administrator may register the recipient too, and a changed
	 * one would send new orders, with customers' addresses, somewhere else.
	 *
	 * @return array{string, list<string>} SQL with placeholders, and their values.
	 */
	private function reachable(): array {
		global $wpdb;

		$emails = [];

		foreach ( WC()->mailer()->get_emails() as $email ) {
			$emails[] = 'admin_texts_woocommerce_' . $email->id . '_settings';
		}

		$whole_placeholders = implode( ', ', array_fill( 0, count( self::WHOLE_CONTEXTS ), '%s' ) );
		$email_placeholders = implode( ', ', array_fill( 0, max( 1, count( $emails ) ), '%s' ) );
		$email_names        = array_map( static fn ( string $name ): string => '%]' . $wpdb->esc_like( $name ) . '%', self::EMAIL_TEXTS );
		$name_conditions    = implode( ' OR ', array_fill( 0, count( $email_names ), 's.name LIKE %s' ) );

		return [
			"( s.context IN ( {$whole_placeholders} ) OR s.context LIKE %s OR ( s.context IN ( {$email_placeholders} ) AND ( {$name_conditions} ) ) )",
			[ ...self::WHOLE_CONTEXTS, $wpdb->esc_like( self::CONTEXT_PREFIX ) . '%', ...( [] !== $emails ? $emails : [ '' ] ), ...$email_names ],
		];
	}

	private static function from_row( object $row ): TranslatableString {
		return new TranslatableString(
			(int) $row->id,
			(string) $row->context,
			(string) $row->name,
			(string) $row->value,
			(string) $row->language,
			isset( $row->translation ) ? (string) $row->translation : null
		);
	}
}
