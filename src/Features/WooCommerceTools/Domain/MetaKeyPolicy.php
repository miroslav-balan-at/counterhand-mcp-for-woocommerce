<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Which custom fields an agent may read and write.
 *
 * Meta is the one place in this plugin where WooCommerce's own permission check
 * is not enough. A customer is a WordPress user and a product is a post, so
 * their "custom fields" live in usermeta and postmeta — the same tables
 * WordPress keeps roles, capabilities and login sessions in. WooCommerce
 * authorises the *object* ("may this user edit customers?") and then writes
 * whatever keys it is handed. The key itself therefore has to be judged, and
 * this class is where that happens.
 *
 * It judges by asking WordPress and WooCommerce wherever they already have an
 * answer, and states a rule of its own only where neither does:
 *
 * - **The underscore rule is core's**, via is_protected_meta(). Not a
 *   str_starts_with() of our own: core's version is filterable, so a store that
 *   deliberately exposes one of its own keys is honoured, and WooCommerce
 *   itself draws the same line — WC_REST_Product_Custom_Fields_Controller lists
 *   custom fields with `meta_key NOT LIKE '\_%'`.
 * - **The serialization rule is core's**, via is_serialized(). Storing a
 *   serialized payload someone else may later unserialize is the classic object
 *   injection setup, and core already knows precisely what serialized looks
 *   like.
 * - **The reserved-key list is ours**, because nothing in core or WooCommerce
 *   publishes one. It is the documented privilege-escalation class: plugins
 *   whose restricted-field lists omitted wp_capabilities have repeatedly
 *   shipped escalation-to-administrator CVEs.
 *
 * Two things were verified against WooCommerce 10.9.4 rather than assumed:
 * writing session_tokens through the customers endpoint **succeeds**, which is
 * an account-takeover primitive and the reason the list exists at all; and the
 * capabilities and user_level writes were ignored somewhere upstream. They stay
 * denied regardless — "another layer happens to stop it today" is not a
 * security property.
 *
 * The table prefix is injected rather than read from $wpdb, because it is
 * genuinely per-install: the store this was verified against prefixes with
 * hms_, so a hardcoded wp_capabilities denial would have protected nothing.
 */
final readonly class MetaKeyPolicy {

	/** Long enough for any honest custom field, short enough not to be a payload. */
	private const MAX_VALUE_BYTES = 65536;

	/**
	 * Keys WordPress gives meaning to, matched as a suffix because the prefix
	 * varies per install and per site in a network.
	 *
	 * A third-party key that genuinely ends in _capabilities is denied too. That
	 * is the right side to err on, and the agent is told exactly why.
	 */
	private const RESERVED_SUFFIX = '/(^|_)(capabilities|user_level|session_tokens)$/i';

	/** Unprefixed keys that carry authentication or password-reset state. */
	private const RESERVED_EXACT = [
		'session_tokens',
		'default_password_nonce',
		'use_ssl',
		'user_activation_key',
		'password_reset_key',
	];

	private const KEY_FORMAT = '/^[A-Za-z][A-Za-z0-9_\-]{0,254}$/';

	public function __construct( private string $table_prefix ) {}

	/**
	 * Reads are looser than writes, but never looser about the reserved keys:
	 * returning a session token is as good as handing over the account.
	 */
	public function may_read( string $key, MetaOwner $owner ): Verdict {
		if ( $this->is_reserved( $key ) ) {
			return Verdict::deny(
				sprintf( '"%s" holds WordPress account or session state and is never readable through this API.', $key )
			);
		}

		if ( is_protected_meta( $key, $owner->meta_type() ) ) {
			return Verdict::deny(
				sprintf( '"%s" is marked private by WordPress. Read the resource itself for the values that have a public field.', $key )
			);
		}

		return Verdict::allow();
	}

	/**
	 * @param mixed $value The value as the agent supplied it; null means delete.
	 */
	public function may_write( string $key, mixed $value, MetaOwner $owner ): Verdict {
		if ( $this->is_reserved( $key ) ) {
			return Verdict::deny(
				sprintf( '"%s" controls WordPress accounts, roles or login sessions. Writing it is never permitted, and no rewording of the key will change that.', $key )
			);
		}

		if ( 1 !== preg_match( self::KEY_FORMAT, $key ) ) {
			return Verdict::deny(
				sprintf(
					'"%s" is not a usable custom field name. Names must start with a letter and use only letters, numbers, underscores and hyphens.',
					$key
				)
			);
		}

		if ( is_protected_meta( $key, $owner->meta_type() ) ) {
			return Verdict::deny(
				sprintf(
					'"%s" is marked private by WordPress, and WooCommerce keeps its own bookkeeping in fields like these. Writing one can leave the resource inconsistent with what the API reports.',
					$key
				)
			);
		}

		return $this->may_hold( $key, $value );
	}

	/** Whether the value itself is acceptable, once the key has passed. */
	private function may_hold( string $key, mixed $value ): Verdict {
		if ( null === $value ) {
			return Verdict::allow();
		}

		if ( is_scalar( $value ) ) {
			return $this->within_limits( $key, (string) $value );
		}

		if ( is_array( $value ) ) {
			return $this->within_limits( $key, (string) wp_json_encode( $value ) );
		}

		return Verdict::deny(
			sprintf( 'The value for "%s" must be a string, number, boolean or list.', $key )
		);
	}

	private function within_limits( string $key, string $text ): Verdict {
		if ( is_serialized( $text ) ) {
			return Verdict::deny(
				sprintf(
					'The value for "%s" is PHP serialized data. Send the value itself — a string, number or list — and let WooCommerce store it.',
					$key
				)
			);
		}

		if ( strlen( $text ) > self::MAX_VALUE_BYTES ) {
			return Verdict::deny(
				sprintf( 'The value for "%s" is larger than the %d KB a custom field may hold.', $key, self::MAX_VALUE_BYTES / 1024 )
			);
		}

		return Verdict::allow();
	}

	private function is_reserved( string $key ): bool {
		if ( in_array( strtolower( $key ), self::RESERVED_EXACT, true ) ) {
			return true;
		}

		if ( 1 === preg_match( self::RESERVED_SUFFIX, $key ) ) {
			return true;
		}

		// This site's own keys, spelled out, so a future loosening of the
		// suffix pattern above cannot quietly expose them.
		return in_array(
			$key,
			[ $this->table_prefix . 'capabilities', $this->table_prefix . 'user_level' ],
			true
		);
	}
}
