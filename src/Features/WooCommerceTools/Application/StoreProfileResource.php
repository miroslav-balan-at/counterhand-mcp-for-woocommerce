<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Application;

use Counterhand\Features\McpServer\ResourceDefinition;
use Counterhand\Features\McpServer\ResourceSourceInterface;
use Counterhand\Features\McpServer\ToolDispatcherInterface;
use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Tokens\Domain\ScopeSummary;
use Counterhand\Shared\Tool\ToolInterface;
use Counterhand\Shared\StoreName;

defined( 'ABSPATH' ) || exit;

/**
 * What a model needs to orient itself, so it stops calling five tools first.
 *
 * Held to what the connection can already reach. The areas are this grant's, not the
 * store's, so it does not reveal which plugins run here, and shipping zones appear only
 * for a grant that could list them anyway.
 */
final readonly class StoreProfileResource implements ResourceSourceInterface {

	private const URI = 'counterhand://store/profile';

	private const NEEDS_TOOL = 'get_store_overview';

	private const ZONES_TOOL = 'get_shipping_zones';

	public function __construct( private ToolDispatcherInterface $tools ) {}

	public function resources_for( AuthenticatedAgent $agent ): array {
		if ( ! in_array( self::NEEDS_TOOL, $this->reachable( $agent ), true ) ) {
			return [];
		}

		return [
			new ResourceDefinition(
				self::URI,
				'store-profile',
				__( 'Store profile', 'counterhand-mcp-for-woocommerce' ),
				__( 'Currency, country, how prices treat tax, units, and what this connection can reach.', 'counterhand-mcp-for-woocommerce' ),
				'application/json'
			),
		];
	}

	public function read( string $uri, AuthenticatedAgent $agent ): ?string {
		$reachable = $this->reachable( $agent );

		if ( self::URI !== $uri || ! in_array( self::NEEDS_TOOL, $reachable, true ) ) {
			return null;
		}

		return (string) wp_json_encode(
			[
				'name'               => StoreName::plain(),
				'url'                => home_url(),
				'currency'           => get_option( 'woocommerce_currency' ),
				'country'            => get_option( 'woocommerce_default_country' ),
				'timezone'           => wp_timezone_string(),
				'locale'             => get_locale(),
				'taxes_enabled'      => 'yes' === get_option( 'woocommerce_calc_taxes' ),
				'prices_include_tax' => 'yes' === get_option( 'woocommerce_prices_include_tax' ),
				'weight_unit'        => get_option( 'woocommerce_weight_unit' ),
				'dimension_unit'     => get_option( 'woocommerce_dimension_unit' ),
				'this_connection'    => ScopeSummary::of( $agent->scopes() )->live_labels(),
				...( in_array( self::ZONES_TOOL, $reachable, true ) ? [ 'shipping_zones' => $this->shipping_zones() ] : [] ),
			]
		);
	}

	/** @return list<string> */
	private function reachable( AuthenticatedAgent $agent ): array {
		return array_map( static fn ( ToolInterface $tool ): string => $tool->name(), $this->tools->visible_for( $agent ) );
	}

	/** @return list<string> */
	private function shipping_zones(): array {
		return array_values( array_map( static fn ( array $zone ): string => (string) $zone['zone_name'], \WC_Shipping_Zones::get_zones() ) );
	}
}
