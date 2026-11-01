<?php

declare( strict_types=1 );

namespace Counterhand\Features\UrlCoupons;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestGatewayInterface;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Written by hand because a coupon's link is protected meta that MetaTool refuses. Writes
 * still go through the coupon route, because the plugin only activates a link when
 * WooCommerce's REST save tells it to, and that route runs the permission check.
 */
final readonly class CouponUrlTool implements ToolInterface {

	private const LIST_NAME = 'get_coupon_urls';

	private const SET_NAME = 'set_coupon_url';

	private const ACTIVE_OPTION = 'wc_url_coupons_active_urls';

	private const PREFIX_OPTION = 'wc_url_coupons_url_prefix';

	private function __construct(
		private string $tool_name,
		private RestGatewayInterface $gateway,
		private CouponUrlRules $rules,
		private IntegrationGroup $group,
	) {}

	/** @return list<self> */
	public static function both( RestGatewayInterface $gateway, IntegrationGroup $group ): array {
		return [
			new self( self::LIST_NAME, $gateway, new CouponUrlRules(), $group ),
			new self( self::SET_NAME, $gateway, new CouponUrlRules(), $group ),
		];
	}

	public function name(): string {
		return $this->tool_name;
	}

	public function description(): string {
		return $this->is_read_only()
			? 'List the coupons that have a link: its path, and the page or product it leads to. Opening the link applies the coupon.'
			: 'Give an existing coupon a link that applies it for whoever opens it, then sends them to redirect_page. An empty url removes the link. The link captures every address starting with it, so anything that is the start of an address the store already uses is refused. Keep it distinctive, and check it is not cached by a page cache.';
	}

	public function input_schema(): array {
		if ( $this->is_read_only() ) {
			return [
				'type'                 => 'object',
				'properties'           => [],
				'additionalProperties' => false,
			];
		}

		return [
			'type'                 => 'object',
			'properties'           => [
				'id'            => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'The coupon.',
				],
				'url'           => [
					'type'        => 'string',
					'description' => 'The path on this store, such as summer-sale. Empty removes the link.',
				],
				'redirect_page' => [
					'type'        => 'integer',
					'description' => 'Id of the published page or product to send visitors to. Required unless removing the link.',
				],
			],
			'required'             => [ 'id', 'url' ],
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
		return function_exists( 'wc_url_coupons' ) && (bool) wc_rest_check_post_permissions( 'shop_coupon', $this->is_read_only() ? 'read' : 'create' );
	}

	public function requires_confirmation(): bool {
		return ! $this->is_read_only();
	}

	public function is_read_only(): bool {
		return self::LIST_NAME === $this->tool_name;
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
		return $this->is_read_only() ? $this->list_urls() : $this->set_url( $arguments );
	}

	/** @return array<string, mixed> */
	private function list_urls(): array {
		// Asks WooCommerce's permission check; a token outlives the role its user held.
		$this->gateway->dispatch(
			RestRoute::wc( '/coupons' ),
			RestMethod::Get,
			[
				'per_page' => 1,
				'_fields'  => 'id',
			]
		);

		$links = [];

		foreach ( $this->active() as $id => $entry ) {
			$links[] = [
				'coupon_id'     => (int) $id,
				'code'          => wc_get_coupon_code_by_id( (int) $id ),
				'url'           => (string) ( $entry['url'] ?? '' ),
				'redirect_page' => (int) ( $entry['redirect'] ?? 0 ),
			];
		}

		return [
			'links' => $links,
			'count' => count( $links ),
		];
	}

	/**
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 * @throws ToolCallException When the link may not be used, or the plugin did not activate it.
	 */
	private function set_url( array $arguments ): array {
		$id       = (int) ( $arguments['id'] ?? 0 );
		$url      = (string) ( $arguments['url'] ?? '' );
		$redirect = (int) ( $arguments['redirect_page'] ?? 0 );
		$prefix   = strtolower( (string) get_option( self::PREFIX_OPTION, '' ) );

		if ( '' !== $url ) {
			$refusal = $this->rules->refusal( $url, $prefix, $id, $this->active() );

			if ( null !== $refusal ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
				throw new ToolCallException( $refusal );
			}

			if ( ! $this->rules->is_public_destination( $redirect ) ) {
				throw new ToolCallException( 'redirect_page must be the id of a published page or product.' );
			}
		}

		$this->gateway->dispatch(
			RestRoute::wc( '/coupons/{id}' ),
			RestMethod::Put,
			[
				'id'        => $id,
				'meta_data' => [
					[
						'key'   => '_wc_url_coupons_unique_url',
						'value' => $url,
					],
					[
						'key'   => '_wc_url_coupons_redirect_page',
						'value' => $redirect,
					],
					[
						'key'   => '_wc_url_coupons_redirect_page_type',
						'value' => '' === $url ? '' : (string) get_post_type( $redirect ),
					],
					[
						'key'   => '_wc_url_coupons_url_prefix',
						'value' => $prefix,
					],
					// No value deletes them. Left behind, the admin form shows the other mode, and saves it.
					[ 'key' => '_wc_url_coupons_existing_page' ],
					[ 'key' => '_wc_url_coupons_existing_page_type' ],
				],
				'_fields'   => 'id',
			]
		);

		$this->apply_prefix( $id, $prefix );

		if ( ( $this->active()[ $id ]['url'] ?? '' ) !== $url ) {
			throw new ToolCallException( 'URL Coupons did not apply this change.' );
		}

		return [
			'coupon_id'     => $id,
			'url'           => $url,
			'link'          => '' === $url ? '' : home_url( '/' . ( '' === $prefix ? '' : $prefix . '/' ) . $url ),
			'redirect_page' => '' === $url ? 0 : $redirect,
		];
	}

	/** The plugin's REST save leaves the store's prefix out of the active link. */
	private function apply_prefix( int $id, string $prefix ): void {
		$active = (array) get_option( self::ACTIVE_OPTION, [] );

		if ( '' === $prefix || ! is_array( $active[ $id ] ?? null ) ) {
			return;
		}

		$active[ $id ]['prefix'] = $prefix;
		update_option( self::ACTIVE_OPTION, $active );
		delete_transient( self::ACTIVE_OPTION );
	}

	/** @return array<array-key, array<string, mixed>> */
	private function active(): array {
		return array_filter( (array) get_option( self::ACTIVE_OPTION, [] ), 'is_array' );
	}
}
