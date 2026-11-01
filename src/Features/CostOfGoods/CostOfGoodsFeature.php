<?php

declare( strict_types=1 );

namespace Counterhand\Features\CostOfGoods;

use Counterhand\Features\McpServer\ToolRegistrarInterface;
use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\Tokens\Domain\ScopeGroups;
use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

final readonly class CostOfGoodsFeature implements FeatureInterface {

	public function __construct(
		private ToolRegistrarInterface $tool_registry,
		private CostLedgerInterface $ledger,
	) {}

	public function register(): void {
		foreach ( CostOperation::cases() as $operation ) {
			$this->tool_registry->add( new CostOfGoodsTool( $operation, $this->ledger, self::group() ) );
		}

		add_filter( ScopeGroups::INTEGRATIONS_FILTER, [ $this, 'with_group' ] );
	}

	/**
	 * The plugin defers loading its classes, so this is asked when the groups are.
	 *
	 * @param  mixed $groups What earlier filters returned.
	 * @return array<mixed>
	 */
	public function with_group( mixed $groups ): array {
		$groups = is_array( $groups ) ? $groups : [];

		return $this->ledger->is_installed() ? [ ...$groups, self::group() ] : $groups;
	}

	public static function group(): IntegrationGroup {
		return IntegrationGroup::of( 'woocommerce-cost-of-goods', copy: new CostOfGoodsCopy() );
	}
}
