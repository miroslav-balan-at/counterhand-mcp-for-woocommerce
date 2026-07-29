<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\WooCommerceTools\Application;

use AgentGateMcp\Features\Tokens\Domain\ApiScope;
use AgentGateMcp\Features\WooCommerceTools\Application\ToolFactory;
use AgentGateMcp\Features\WooCommerceTools\Domain\DescriptorProvider;
use AgentGateMcp\Features\WooCommerceTools\Domain\MetaKeyPolicy;
use AgentGateMcp\Features\WooCommerceTools\Domain\FieldProfile;
use AgentGateMcp\Features\WooCommerceTools\Domain\Operation;
use AgentGateMcp\Features\WooCommerceTools\Domain\OperationDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ResourceDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ToolName;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestRoute;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\SchemaProvider;
use AgentGateMcp\Shared\Tool\ToolGroup;
use AgentGateMcp\Shared\Tool\ToolInterface;
use AgentGateMcp\Tests\Doubles\FakeRestGateway;
use AgentGateMcp\Tests\Unit\TestCase;

/**
 * Building the surface must stay pure: no WooCommerce call may happen here,
 * because registration runs at plugins_loaded and forcing the route table that
 * early would fire rest_api_init before WooCommerce has registered its post
 * types — leaving a half-empty catalog memoized for the rest of the request.
 * Nothing in this test stubs a WordPress function, and that is the assertion.
 */
final class ToolFactoryTest extends TestCase {

	private function factory(): ToolFactory {
		$catalog = new RouteCatalog();

		return new ToolFactory(
			new FakeRestGateway(),
			$catalog,
			new RoutePermissionProbe( $catalog ),
			new SchemaProvider( $catalog ),
			new MetaKeyPolicy( 'wp_' )
		);
	}

	/** @param list<Operation> $operations */
	private function descriptors( ToolGroup $group, array $operations ): DescriptorProvider {
		$resource = new ResourceDescriptor(
			'coupons',
			$group,
			RestRoute::wc( '/coupons' ),
			RestRoute::wc( '/coupons/{id}' ),
			'coupon',
			'coupons',
			array_map(
				static fn ( Operation $operation ): OperationDescriptor => new OperationDescriptor(
					ToolName::from( $operation->value ),
					$operation,
					FieldProfile::everything()
				),
				$operations
			)
		);

		return new class( $resource ) implements DescriptorProvider {

			public function __construct( private readonly ResourceDescriptor $resource ) {}

			public function resources(): array {
				return [ $this->resource ];
			}
		};
	}

	public function test_one_tool_is_built_per_declared_operation(): void {
		$tools = $this->factory()->tools(
			$this->descriptors( ToolGroup::Coupons, [ Operation::GetItems, Operation::GetItem, Operation::CreateItem ] )
		);

		$this->assertCount( 3, $tools );
		$this->assertContainsOnlyInstancesOf( ToolInterface::class, $tools );
		$this->assertSame(
			[ 'get_items', 'get_item', 'create_item' ],
			array_map( static fn ( ToolInterface $tool ): string => $tool->name(), $tools )
		);
	}

	public function test_a_read_operation_asks_for_the_groups_read_scope(): void {
		$tools = $this->factory()->tools( $this->descriptors( ToolGroup::Coupons, [ Operation::GetItems ] ) );

		$this->assertSame( ApiScope::CouponsRead, $tools[0]->required_scope() );
		$this->assertSame( ToolGroup::Coupons, $tools[0]->group() );
	}

	public function test_every_write_operation_asks_for_the_groups_write_scope(): void {
		$tools = $this->factory()->tools(
			$this->descriptors( ToolGroup::Coupons, [ Operation::CreateItem, Operation::UpdateItem, Operation::DeleteItem ] )
		);

		foreach ( $tools as $tool ) {
			$this->assertSame( ApiScope::CouponsWrite, $tool->required_scope() );
		}
	}

	/**
	 * Reports are read-only in WooCommerce, so there is no scope to gate a write
	 * with — and therefore no safe way to offer it. Better to fail while the
	 * catalog is being edited than to discover it on someone's store.
	 */
	public function test_a_write_on_a_read_only_group_refuses_to_be_built(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'create_item' );

		$this->factory()->tools( $this->descriptors( ToolGroup::Reports, [ Operation::CreateItem ] ) );
	}

	public function test_a_read_on_a_read_only_group_is_fine(): void {
		$tools = $this->factory()->tools( $this->descriptors( ToolGroup::Reports, [ Operation::GetItems ] ) );

		$this->assertSame( ApiScope::ReportsRead, $tools[0]->required_scope() );
	}

	public function test_a_catalog_with_no_resources_builds_no_tools(): void {
		$empty = new class() implements DescriptorProvider {

			public function resources(): array {
				return [];
			}
		};

		$this->assertSame( [], $this->factory()->tools( $empty ) );
	}
}
