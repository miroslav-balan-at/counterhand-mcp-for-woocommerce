<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Abilities;

use Brain\Monkey\Functions;
use Counterhand\Features\Abilities\AbilityCatalog;
use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Tests\Unit\TestCase;

final class AbilityCatalogTest extends TestCase {

	/** @param list<\WP_Ability> $abilities */
	private function catalog( array $abilities, bool $after_init = true ): AbilityCatalog {
		Functions\when( 'did_action' )->justReturn( $after_init ? 1 : 0 );
		Functions\when( 'wp_get_abilities' )->justReturn( $abilities );

		return new AbilityCatalog();
	}

	/** @param array<string, mixed> $annotations */
	private function exposed( string $name, array $annotations = [ 'readonly' => true ] ): \WP_Ability {
		return new \WP_Ability(
			$name,
			[],
			[
				'annotations' => $annotations,
				'mcp'         => [ 'public' => true ],
			]
		);
	}

	/** @return list<string> */
	private function names( AbilityCatalog $catalog ): array {
		return array_map( static fn ( \WP_Ability $ability ): string => $ability->get_name(), $catalog->abilities() );
	}

	public function test_either_exposure_flag_counts_and_neither_does_not(): void {
		$catalog = $this->catalog(
			[
				new \WP_Ability( 'seo-plugin/get', [], [ 'public' => true ] ),
				new \WP_Ability( 'yoast-seo/get', [], [ 'mcp' => [ 'public' => true ] ] ),
				new \WP_Ability( 'wpforms/create', [], [ 'mcp' => [ 'public' => false ] ] ),
				new \WP_Ability( 'some-plugin/private' ),
			]
		);

		$this->assertSame( [ 'seo-plugin/get', 'yoast-seo/get' ], $this->names( $catalog ) );
	}

	/**
	 * woocommerce/ would route around the products and orders scopes, and the
	 * adapter's execute-ability would route around everything. core/'s environment
	 * report is what system:read gates.
	 */
	public function test_namespaces_that_would_bypass_a_gate_are_never_bridged(): void {
		$catalog = $this->catalog(
			[
				$this->exposed( 'woocommerce/product-update' ),
				$this->exposed( 'core/get-environment-info' ),
				$this->exposed( 'mcp-adapter/execute-ability' ),
				$this->exposed( 'counterhand/anything' ),
			]
		);

		$this->assertSame( [], $catalog->abilities() );
	}

	public function test_nothing_is_asked_of_core_before_init(): void {
		Functions\when( 'did_action' )->justReturn( 0 );
		Functions\expect( 'wp_get_abilities' )->never();

		$this->assertSame( [], ( new AbilityCatalog() )->abilities() );
	}

	public function test_one_group_per_plugin_in_name_order(): void {
		$catalog = $this->catalog( [ $this->exposed( 'yoast-seo/get' ), $this->exposed( 'wpforms/list' ), $this->exposed( 'yoast-seo/score' ) ] );

		$this->assertSame(
			[ 'integration:wpforms', 'integration:yoast-seo' ],
			array_map( static fn ( IntegrationGroup $group ): string => $group->key(), $catalog->groups() )
		);
	}

	public function test_a_plugin_offering_only_reads_has_no_write_switch(): void {
		$catalog = $this->catalog( [ $this->exposed( 'wpforms/list' ), $this->exposed( 'yoast-seo/get' ), $this->exposed( 'yoast-seo/update', [ 'readonly' => false ] ) ] );

		[ $wpforms, $yoast ] = $catalog->groups();

		$this->assertFalse( $wpforms->has_write() );
		$this->assertTrue( $yoast->has_write() );
	}

	public function test_a_throwing_plugin_costs_the_abilities_not_the_store(): void {
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'wp_get_abilities' )->alias(
			static function (): array {
				throw new \RuntimeException( 'A plugin broke.' );
			}
		);

		$catalog = new AbilityCatalog();

		$this->assertSame( [], $catalog->abilities() );
		$this->assertSame( [], $catalog->groups() );
	}

	public function test_a_gated_ability_declaring_its_own_confirm_is_not_offered(): void {
		$catalog = $this->catalog(
			[
				new \WP_Ability(
					'some-plugin/wipe',
					[
						'type'       => 'object',
						'properties' => [ 'confirm' => [ 'type' => 'string' ] ],
					],
					[
						'annotations' => [ 'readonly' => false ],
						'public'      => true,
					]
				),
			]
		);

		$this->assertSame( [], $catalog->abilities() );
	}

	public function test_a_known_plugin_keeps_its_own_spelling(): void {
		[ $group ] = $this->catalog( [ $this->exposed( 'yoast-seo/get' ) ] )->groups();

		$this->assertSame( 'Yoast SEO', $group->label() );
	}
}
