<?php

declare( strict_types=1 );

namespace Counterhand\Features\Abilities;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\WooCommerceTools\Domain\ArgumentPolicy;

defined( 'ABSPATH' ) || exit;

/**
 * Which abilities on this store may become tools, and the groups they fall into.
 *
 * Grouped by namespace rather than category: the namespace is the plugin, while
 * categories are the plugin's own filing (`wpforms-forms`) or shared (`site`).
 */
final class AbilityCatalog {

	/**
	 * woocommerce/*: all seven duplicate generated tools, and bridging them would
	 * let an integration grant reach products and orders past those groups' scopes.
	 * core/*: core is no plugin, and it would add a separate grant for environment data.
	 * contact-form-7/*, flamingo/*: served by our own tools with their policies, which
	 * abilities from either plugin would bypass (CF7 is discussing them).
	 * mcp-adapter/*: meta-tools that call any ability, which would bypass every gate.
	 */
	private const SKIPPED_NAMESPACES = [ 'woocommerce', 'core', 'mcp-adapter', 'counterhand', 'contact-form-7', 'flamingo' ];

	/**
	 * Each read and returned more than its plugin's scope should reach:
	 * get-settings holds facebook_secret; get-system-status the database user, host and
	 * name, which WordPress marks private; get-seo-scores titles of any post type,
	 * which for shop_coupon are coupon codes; analyze-post-content full post bodies;
	 * get-llms-txt sends its own Content-Type header into our response.
	 */
	private const SKIPPED_ABILITIES = [
		'rank-math/get-settings',
		'rank-math/get-system-status',
		'rank-math/get-seo-scores',
		'rank-math/analyze-post-content',
		'rank-math/get-llms-txt',
	];

	/** @var ?list<\WP_Ability> */
	private ?array $abilities = null;

	/** @var ?array<string, IntegrationGroup> By namespace. */
	private ?array $groups = null;

	/** @return list<\WP_Ability> */
	public function abilities(): array {
		// Asking before init is an error in core, and the answer would be empty.
		if ( ! did_action( 'init' ) ) {
			return [];
		}

		return $this->abilities ??= $this->discovered();
	}

	public function group_of( \WP_Ability $ability ): ?IntegrationGroup {
		$this->groups ??= $this->built_groups();

		return $this->groups[ self::namespace_of( $ability ) ] ?? null;
	}

	/** @return list<IntegrationGroup> */
	public function groups(): array {
		// Before init abilities() is empty, and that must not be remembered.
		if ( ! did_action( 'init' ) ) {
			return [];
		}

		$this->groups ??= $this->built_groups();

		return array_values( $this->groups );
	}

	/**
	 * Registration runs every plugin's callback, and one that throws must cost only
	 * the abilities, not the screen or the endpoint asking.
	 *
	 * @return list<\WP_Ability>
	 */
	private function discovered(): array {
		try {
			return array_values( array_filter( wp_get_abilities(), [ self::class, 'is_exposed' ] ) );
		} catch ( \Throwable $throwable ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( 'Reading abilities failed: ' . $throwable->getMessage(), [ 'source' => 'counterhand-mcp' ] );
			}

			return [];
		}
	}

	/**
	 * Writable only when the plugin actually offers a write, so the settings row does
	 * not show a switch that does nothing.
	 *
	 * @return array<string, IntegrationGroup>
	 */
	private function built_groups(): array {
		$writable = [];

		foreach ( $this->abilities() as $ability ) {
			$namespace              = self::namespace_of( $ability );
			$writable[ $namespace ] = ( $writable[ $namespace ] ?? false ) || ! AbilityAnnotations::of( $ability )->read_only;
		}

		$groups = [];

		foreach ( $writable as $namespace => $is_writable ) {
			$group = IntegrationGroup::try_of( $namespace, $is_writable, new KnownPluginCopy( $namespace ) );

			if ( null !== $group ) {
				$groups[ $namespace ] = $group;
			}
		}

		ksort( $groups );

		return $groups;
	}

	/**
	 * The official adapter's rule: meta.mcp.public decides when set, meta.public is the
	 * fallback, so a plugin opting out of MCP is respected. A malformed meta.mcp fails
	 * closed, and a resource or prompt is not a tool.
	 */
	private static function is_exposed( \WP_Ability $ability ): bool {
		$meta = $ability->get_meta();
		$mcp  = $meta['mcp'] ?? [];

		if (
			in_array( self::namespace_of( $ability ), self::SKIPPED_NAMESPACES, true )
			|| in_array( $ability->get_name(), self::SKIPPED_ABILITIES, true )
			|| ! is_array( $mcp )
			|| 'tool' !== ( $mcp['type'] ?? 'tool' )
			|| self::collides_with_confirmation( $ability )
		) {
			return false;
		}

		return true === ( $mcp['public'] ?? $meta['public'] ?? null );
	}

	/** What an ability's arguments must not say, where its own checks do not cover it. */
	public function policy_of( \WP_Ability $ability ): ?ArgumentPolicy {
		return 'yoast-seo/update-post-seo-data' === $ability->get_name() ? new YoastFieldPolicy() : null;
	}

	/**
	 * The pipeline takes `confirm` away before execute(), so a gated ability declaring
	 * its own could never receive it and would fail every call. Better not offered.
	 */
	private static function collides_with_confirmation( \WP_Ability $ability ): bool {
		$properties = $ability->get_input_schema()['properties'] ?? [];

		return ! AbilityAnnotations::of( $ability )->read_only && is_array( $properties ) && isset( $properties['confirm'] );
	}

	private static function namespace_of( \WP_Ability $ability ): string {
		return explode( '/', $ability->get_name() )[0];
	}
}
