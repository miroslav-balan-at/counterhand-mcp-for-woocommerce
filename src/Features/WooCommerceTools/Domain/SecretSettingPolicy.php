<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Refuses writes to settings whose id looks like a credential.
 *
 * WooCommerce's settings API is a single generic endpoint over every option a
 * store and its plugins register, and payment extensions keep their live API
 * credentials there — a Stripe secret key is a setting exactly like the shop's
 * postal address is. So "may write settings" is far too coarse a grant to be
 * the last word, and this narrows it.
 *
 * A name-shaped denylist rather than a list of known ids, deliberately: the ids
 * come from whichever plugins a store happens to run, so an allowlist would be
 * wrong on every store but ours and a list of known ids would go stale the day
 * a new gateway is installed. Matching on the name is imprecise in the harmless
 * direction — a setting called "key_visual" is refused and the agent is told
 * why — and precise in the direction that matters.
 *
 * This does not pretend to be a security boundary against a determined caller
 * who already holds settings:write. It is there so that an agent asked to
 * "update the store settings" cannot casually overwrite a live payment
 * credential, which is a mistake far likelier than an attack.
 */
final readonly class SecretSettingPolicy implements ArgumentPolicy {

	/**
	 * Word-boundary matched so "secret", "api_key" and "auth_token" are caught
	 * while "keyword" and "monkey" are not.
	 */
	private const SECRET_SHAPED = '/(^|[_\-])(secret|secrets|key|keys|apikey|password|passwd|pass|token|tokens|credential|credentials|salt|nonce|private|signature)([_\-]|$)/i';

	public function __construct( private string $argument = 'id' ) {}

	public function verdict( array $arguments ): Verdict {
		$id = (string) ( $arguments[ $this->argument ] ?? '' );

		if ( '' === $id ) {
			return Verdict::allow();
		}

		/**
		 * Filters whether one setting id may be written through an MCP tool.
		 *
		 * The escape hatch for a store whose own naming trips the pattern, and
		 * the tightening point for one that wants a specific id off limits.
		 *
		 * @param bool   $writable Whether the name-shaped check allowed it.
		 * @param string $id       The setting id being written.
		 */
		$writable = (bool) apply_filters(
			'agmcp_setting_writable',
			1 !== preg_match( self::SECRET_SHAPED, $id ),
			$id
		);

		if ( $writable ) {
			return Verdict::allow();
		}

		return Verdict::deny(
			sprintf(
				'"%s" is named like a credential — an API key, secret, password or token — and this API will not write those. Ask the store owner to change it in the WooCommerce admin, where they can see what they are pasting.',
				$id
			)
		);
	}
}
