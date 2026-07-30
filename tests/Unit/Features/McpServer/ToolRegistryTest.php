<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\McpServer;

use Counterhand\Features\McpServer\ToolRegistry;
use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Doubles\AgentFactory;
use Counterhand\Tests\Doubles\StubTool;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The registry is the authorization surface: the same filtered set must back
 * tools/list and tools/call, so a tool that is toggled off or out of scope is
 * unreachable rather than merely hidden.
 */
final class ToolRegistryTest extends TestCase {

	private function registry( array $settings ): ToolRegistry {
		Functions\when( 'get_option' )->justReturn( $settings );

		return new ToolRegistry( new PluginSettings() );
	}

	private function read_tool(): StubTool {
		return new StubTool( 'list_products', ApiScope::ProductsRead, ToolGroup::Products );
	}

	private function write_tool(): StubTool {
		return new StubTool( 'create_product', ApiScope::ProductsWrite, ToolGroup::Products );
	}

	/**
	 * @param  list<\Counterhand\Shared\Tool\ToolInterface> $tools
	 * @return list<string>
	 */
	private function names( array $tools ): array {
		return array_map( static fn ( $tool ): string => $tool->name(), $tools );
	}

	public function test_a_tool_needs_both_its_group_toggle_and_its_scope(): void {
		$registry = $this->registry( [ 'products_read' => true ] );
		$registry->add( $this->read_tool() );

		$granted = AgentFactory::with_scopes( [ 'products:read' ] );
		$this->assertCount( 1, $registry->visible_for( $granted ) );

		$ungranted = AgentFactory::with_scopes( [ 'orders:read' ] );
		$this->assertSame( [], $registry->visible_for( $ungranted ) );
	}

	public function test_read_and_write_axes_are_toggled_independently(): void {
		$registry = $this->registry(
			[
				'products_read'  => true,
				'products_write' => false,
			]
		);
		$registry->add( $this->read_tool() );
		$registry->add( $this->write_tool() );

		$agent = AgentFactory::with_scopes( [ 'products:read', 'products:write' ] );

		$this->assertSame( [ 'list_products' ], $this->names( $registry->visible_for( $agent ) ) );
	}

	public function test_resolve_for_fails_closed_on_a_tool_the_agent_cannot_see(): void {
		$registry = $this->registry(
			[
				'products_read'  => true,
				'products_write' => true,
			]
		);
		$registry->add( $this->write_tool() );

		$agent = AgentFactory::with_scopes( [ 'products:read' ] );

		$this->assertNull(
			$registry->resolve_for( $agent, 'create_product' ),
			'A tool outside the granted scopes must not be resolvable by name.'
		);
	}

	public function test_resolve_for_returns_the_tool_when_every_gate_passes(): void {
		$registry = $this->registry( [ 'products_read' => true ] );
		$tool     = $this->read_tool();
		$registry->add( $tool );

		$agent = AgentFactory::with_scopes( [ 'products:read' ] );

		$this->assertSame( $tool, $registry->resolve_for( $agent, 'list_products' ) );
	}

	public function test_disabling_a_group_hides_it_from_list_and_call_alike(): void {
		$registry = $this->registry( [ 'products_read' => false ] );
		$registry->add( $this->read_tool() );

		$agent = AgentFactory::with_scopes( [ 'products:read' ] );

		$this->assertSame( [], $registry->visible_for( $agent ) );
		$this->assertNull( $registry->resolve_for( $agent, 'list_products' ) );
	}

	public function test_a_tool_wordpress_would_refuse_is_hidden_from_list_and_call_alike(): void {
		$registry = $this->registry( [ 'products_read' => true ] );
		$registry->add( new StubTool( 'list_products', ApiScope::ProductsRead, ToolGroup::Products, available: false ) );

		$agent = AgentFactory::with_scopes( [ 'products:read' ] );

		$this->assertSame( [], $registry->visible_for( $agent ) );
		$this->assertNull( $registry->resolve_for( $agent, 'list_products' ) );
	}

	/**
	 * The capability gate is the only one that can reach the database, so it
	 * runs once per agent however many times the surface is asked for — a
	 * tools/list followed by three tools/call must not re-probe.
	 */
	public function test_the_capability_gate_is_asked_once_per_agent(): void {
		$registry = $this->registry( [ 'products_read' => true ] );
		$tool     = $this->read_tool();
		$registry->add( $tool );

		$agent = AgentFactory::with_scopes( [ 'products:read' ] );

		$registry->visible_for( $agent );
		$registry->resolve_for( $agent, 'list_products' );
		$registry->resolve_for( $agent, 'list_products' );

		$this->assertSame( 1, $tool->availability_checks );
	}

	public function test_the_memo_does_not_serve_one_agents_surface_to_another(): void {
		$registry = $this->registry(
			[
				'products_read' => true,
				'orders_read'   => true,
			]
		);
		$registry->add( $this->read_tool() );
		$registry->add( new StubTool( 'list_orders', ApiScope::OrdersRead, ToolGroup::Orders ) );

		$products = AgentFactory::with_scopes( [ 'products:read' ] );
		$orders   = AgentFactory::with_scopes( [ 'orders:read' ] );

		$this->assertSame( [ 'list_products' ], $this->names( $registry->visible_for( $products ) ) );
		$this->assertSame( [ 'list_orders' ], $this->names( $registry->visible_for( $orders ) ) );
		$this->assertNull( $registry->resolve_for( $orders, 'list_products' ) );
	}

	/** Scope order is a client's choice, not a different grant. */
	public function test_agents_granted_the_same_scopes_in_a_different_order_share_a_surface(): void {
		$registry = $this->registry( [ 'products_read' => true ] );
		$tool     = $this->read_tool();
		$registry->add( $tool );

		$registry->visible_for( AgentFactory::with_scopes( [ 'products:read', 'orders:read' ] ) );
		$registry->visible_for( AgentFactory::with_scopes( [ 'orders:read', 'products:read' ] ) );

		$this->assertSame( 1, $tool->availability_checks );
	}

	public function test_a_tool_registered_after_a_query_still_appears(): void {
		$registry = $this->registry(
			[
				'products_read' => true,
				'orders_read'   => true,
			]
		);
		$registry->add( $this->read_tool() );

		$agent = AgentFactory::with_scopes( [ 'products:read', 'orders:read' ] );
		$this->assertCount( 1, $registry->visible_for( $agent ) );

		$registry->add( new StubTool( 'list_orders', ApiScope::OrdersRead, ToolGroup::Orders ) );

		$this->assertCount( 2, $registry->visible_for( $agent ) );
	}

	/**
	 * A silent overwrite would leave the settings screen advertising one tool's
	 * group and scope while a different class answered the calls.
	 */
	public function test_two_tools_cannot_claim_the_same_name(): void {
		$registry = $this->registry( [ 'products_read' => true ] );
		$registry->add( $this->read_tool() );

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'list_products' );

		$registry->add( new StubTool( 'list_products', ApiScope::OrdersRead, ToolGroup::Orders ) );
	}

	public function test_visible_for_returns_a_list_not_a_keyed_map(): void {
		$registry = $this->registry(
			[
				'products_read' => true,
				'orders_read'   => true,
			]
		);
		$registry->add( $this->read_tool() );
		$registry->add( new StubTool( 'list_orders', ApiScope::OrdersRead, ToolGroup::Orders ) );

		$visible = $registry->visible_for( AgentFactory::with_scopes( [ 'products:read', 'orders:read' ] ) );

		$this->assertSame( [ 0, 1 ], array_keys( $visible ) );
	}
}
