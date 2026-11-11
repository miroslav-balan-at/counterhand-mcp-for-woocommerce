<?php

declare( strict_types=1 );

namespace Counterhand\Features\UrlCoupons;

use Counterhand\Features\McpServer\ToolRegistrarInterface;
use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\Tokens\Domain\ScopeGroups;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestGatewayInterface;
use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

final readonly class UrlCouponsFeature implements FeatureInterface {

	public function __construct(
		private ToolRegistrarInterface $tool_registry,
		private RestGatewayInterface $gateway,
	) {}

	public function register(): void {
		foreach ( CouponUrlTool::both( $this->gateway, self::group() ) as $tool ) {
			$this->tool_registry->add( $tool );
		}

		add_filter( ScopeGroups::INTEGRATIONS_FILTER, [ $this, 'with_group' ] );
		add_filter( ScopeGroups::SUPPORTED_PLUGINS_FILTER, [ $this, 'with_name' ] );
	}

	/**
	 * @param  mixed $names What earlier filters returned.
	 * @return array<mixed>
	 */
	public function with_name( mixed $names ): array {
		return [ ...( is_array( $names ) ? $names : [] ), self::group()->label() ];
	}

	/**
	 * @param  mixed $groups What earlier filters returned.
	 * @return array<mixed>
	 */
	public function with_group( mixed $groups ): array {
		$groups = is_array( $groups ) ? $groups : [];

		return function_exists( 'wc_url_coupons' ) ? [ ...$groups, self::group() ] : $groups;
	}

	public static function group(): IntegrationGroup {
		return IntegrationGroup::of( 'woocommerce-url-coupons', copy: new UrlCouponsCopy() );
	}
}
