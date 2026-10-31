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
		Functions\when( 'did_action' )->justReturn( 1 );

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

	public function test_a_late_source_is_asked_once_and_only_when_the_surface_is_read(): void {
		$registry = $this->registry( [ 'products_read' => true ] );
		$asked    = 0;

		$registry->add_late_source(
			function () use ( &$asked ): array {
				++$asked;

				return [ $this->read_tool() ];
			}
		);
		$this->assertSame( 0, $asked );

		$registry->tool_counts_by_group();
		$registry->tool_counts_by_group();

		$this->assertSame( 1, $asked );
		$this->assertSame( [ 'products' => 1 ], $registry->tool_counts_by_group() );
	}

	/** Names from other plugins: a clash must not take the store's endpoint down. */
	public function test_a_late_tool_with_a_taken_name_is_skipped_rather_than_fatal(): void {
		$registry = $this->registry( [ 'products_read' => true ] );
		$registry->add( $this->read_tool() );
		$registry->add_late_source( fn (): array => [ $this->read_tool() ] );

		$this->assertSame( [ 'products' => 1 ], $registry->tool_counts_by_group() );
	}

	public function test_a_throwing_late_source_leaves_the_rest_standing(): void {
		$registry = $this->registry( [ 'products_read' => true ] );
		$registry->add( $this->read_tool() );
		$registry->add_late_source(
			static function (): array {
				throw new \RuntimeException( 'A plugin broke.' );
			}
		);

		$this->assertSame( [ 'products' => 1 ], $registry->tool_counts_by_group() );
	}

	public function test_a_late_source_read_before_init_is_not_used_up(): void {
		$registry = $this->registry( [ 'products_read' => true ] );
		$registry->add_late_source( fn (): array => [ $this->read_tool() ] );

		Functions\when( 'did_action' )->justReturn( 0 );
		$this->assertSame( [], $registry->tool_counts_by_group() );

		Functions\when( 'did_action' )->justReturn( 1 );
		$this->assertSame( [ 'products' => 1 ], $registry->tool_counts_by_group() );
	}

	/** Calling a WooCommerce tool must run no other plugin's discovery or permission check. */
	public function test_resolving_a_held_tool_asks_no_late_source_and_gates_no_other_tool(): void {
		$registry = $this->registry( [ 'products_read' => true ] );
		$other    = new StubTool( 'get_coupons', ApiScope::ProductsRead, ToolGroup::Products );
		$registry->add( $this->read_tool() );
		$registry->add( $other );
		$asked = false;
		$registry->add_late_source(
			static function () use ( &$asked ): array {
				$asked = true;

				return [];
			}
		);

		$this->assertNotNull( $registry->resolve_for( AgentFactory::with_scopes( [ 'products:read' ] ), 'list_products' ) );
		$this->assertFalse( $asked );
		$this->assertSame( 0, $other->availability_checks );
	}

	private function integration_tool( string $name ): StubTool {
		return new StubTool( $name, \Counterhand\Features\Tokens\Domain\IntegrationGroup::of( 'acme' )->read_scope(), \Counterhand\Features\Tokens\Domain\IntegrationGroup::of( 'acme' ) );
	}

	/** @param list<string> $approved */
	private function agent_who_approved( array $approved ): \Counterhand\Features\Tokens\Authentication\AuthenticatedAgent {
		return new \Counterhand\Features\Tokens\Authentication\AuthenticatedAgent(
			new \Counterhand\Features\Tokens\Domain\ApiToken(
				1,
				\Counterhand\Features\Tokens\Domain\TokenId::try_from_string( 'abcdef0123456789' ),
				'Test token',
				\Counterhand\Features\Tokens\Domain\GrantedScopeSet::from_values( [ 'integration:acme:read', 'products:read' ] ),
				\Counterhand\Features\Tokens\Domain\TokenStatus::Active,
				1,
				new \DateTimeImmutable( '2026-01-01 00:00:00' ),
				null,
				null,
				approved_tools: $approved
			)
		);
	}

	/** A plugin update must not widen what someone already approved. */
	public function test_an_integration_tool_added_after_approval_stays_out_at_list_and_at_call(): void {
		$registry = $this->registry(
			[
				'integration_acme_read' => true,
				'products_read'         => true,
			]
		);
		$registry->add( $this->integration_tool( 'acme_old' ) );
		$registry->add( $this->integration_tool( 'acme_new' ) );
		$registry->add( $this->read_tool() );
		$agent = $this->agent_who_approved( [ 'acme_old' ] );

		$this->assertNull( $registry->resolve_for( $agent, 'acme_new' ), 'refused at the call before any list' );
		$this->assertNotNull( $registry->resolve_for( $agent, 'acme_old' ) );
		$this->assertSame( [ 'acme_old', 'list_products' ], array_map( static fn ( $tool ): string => $tool->name(), $registry->visible_for( $agent ) ) );
		$this->assertNull( $registry->resolve_for( $agent, 'acme_new' ), 'and after one' );
	}

	public function test_a_grant_records_only_the_integration_tools_its_scopes_reach(): void {
		$registry = $this->registry(
			[
				'integration_acme_read' => true,
				'products_read'         => true,
			]
		);
		$registry->add( $this->integration_tool( 'acme_old' ) );
		$registry->add( $this->read_tool() );

		$this->assertSame(
			[ 'integration:acme:read' => [ 'acme_old' ] ],
			$registry->integration_tools_by_scope( \Counterhand\Features\Tokens\Domain\GrantedScopeSet::from_values( [ 'integration:acme:read', 'products:read' ] ) )
		);
		$this->assertSame( [], $registry->integration_tools_by_scope( \Counterhand\Features\Tokens\Domain\GrantedScopeSet::from_values( [ 'products:read' ] ) ) );
	}

	/** Two tokens of one owner with the same scopes can hold different approvals. */
	public function test_the_memo_tells_apart_tokens_that_approved_different_tools(): void {
		$registry = $this->registry( [ 'integration_acme_read' => true ] );
		$registry->add( $this->integration_tool( 'acme_old' ) );

		$this->assertCount( 1, $registry->visible_for( $this->agent_who_approved( [ 'acme_old' ] ) ) );
		$this->assertCount( 0, $registry->visible_for( $this->agent_who_approved( [] ) ) );
	}
}
