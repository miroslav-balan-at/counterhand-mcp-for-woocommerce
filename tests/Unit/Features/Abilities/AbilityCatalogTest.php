<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Abilities;

use Brain\Monkey\Functions;
use Counterhand\Features\Abilities\AbilityCatalog;
use Counterhand\Features\Abilities\YoastFieldPolicy;
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

	public function test_a_write_declaring_its_own_confirm_is_not_offered(): void {
		$catalog = $this->catalog(
			[
				new \WP_Ability(
					'some-plugin/wipe',
					[
						'type'       => 'object',
						'properties' => [ 'confirm' => [ 'type' => 'string' ] ],
					],
					[
						'annotations' => [
							'readonly'    => false,
							'destructive' => false,
						],
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

	/** @dataProvider exposure */
	public function test_the_exposure_rule_is_the_official_adapters( array $meta, bool $exposed ): void {
		$this->assertSame( $exposed ? [ 'some-plugin/get' ] : [], $this->names( $this->catalog( [ new \WP_Ability( 'some-plugin/get', [], $meta ) ] ) ) );
	}

	/** @return iterable<string, array{array<string, mixed>, bool}> */
	public static function exposure(): iterable {
		yield 'mcp.public alone' => [ [ 'mcp' => [ 'public' => true ] ], true ];
		yield 'public alone' => [ [ 'public' => true ], true ];
		yield 'a plugin opting out of MCP wins' => [
			[
				'public' => true,
				'mcp'    => [ 'public' => false ],
			],
			false,
		];
		yield 'mcp.public beats public false' => [
			[
				'public' => false,
				'mcp'    => [ 'public' => true ],
			],
			true,
		];
		yield 'a malformed mcp fails closed' => [
			[
				'public' => true,
				'mcp'    => 'yes',
			],
			false,
		];
		yield 'a prompt is not a tool' => [
			[
				'mcp' => [
					'public' => true,
					'type'   => 'prompt',
				],
			],
			false,
		];
		yield 'a resource is not a tool' => [
			[
				'mcp' => [
					'public' => true,
					'type'   => 'resource',
				],
			],
			false,
		];
	}

	/** Each of these returned more than a plugin's own scope should reach. */
	public function test_rank_math_abilities_that_read_too_much_are_never_bridged(): void {
		$catalog = $this->catalog(
			[
				$this->exposed( 'rank-math/get-settings' ),
				$this->exposed( 'rank-math/get-system-status' ),
				$this->exposed( 'rank-math/get-seo-scores' ),
				$this->exposed( 'rank-math/analyze-post-content' ),
				$this->exposed( 'rank-math/get-llms-txt' ),
				$this->exposed( 'rank-math/get-post-schema' ),
			]
		);

		$this->assertSame( [ 'rank-math/get-post-schema' ], $this->names( $catalog ) );
	}

	/** Our own tools serve them, with policies a second path would bypass. */
	public function test_abilities_from_plugins_we_serve_ourselves_are_never_bridged(): void {
		$this->assertSame( [], $this->catalog( [ $this->exposed( 'contact-form-7/update-form' ), $this->exposed( 'flamingo/get' ) ] )->abilities() );
	}

	public function test_only_yoasts_update_gets_its_policy(): void {
		$catalog = $this->catalog( [] );

		$this->assertInstanceOf( YoastFieldPolicy::class, $catalog->policy_of( new \WP_Ability( 'yoast-seo/update-post-seo-data' ) ) );
		$this->assertNull( $catalog->policy_of( new \WP_Ability( 'yoast-seo/get-post-seo-data' ) ) );
	}
}
