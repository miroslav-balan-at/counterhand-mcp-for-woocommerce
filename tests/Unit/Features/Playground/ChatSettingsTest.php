<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Playground;

use Counterhand\Features\Playground\ChatSettings;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * Which areas the admin chat may reach.
 *
 * Every selected group's schemas ride along on every message, so this selection
 * is the difference between a usable chat and one that hands a model sixty tool
 * definitions and gets worse answers for the trouble.
 */
final class ChatSettingsTest extends TestCase {

	private function settings( mixed $stored ): ChatSettings {
		Functions\when( 'get_option' )->justReturn( $stored );

		return new ChatSettings();
	}

	/** @return list<string> */
	private function slugs( ChatSettings $settings ): array {
		return array_map( static fn ( ToolGroup $group ): string => $group->value, $settings->groups() );
	}

	public function test_an_untouched_install_gets_the_everyday_groups(): void {
		$this->assertSame(
			array_map(
				static fn ( ToolGroup $group ): string => $group->value,
				array_filter( ToolGroup::cases(), static fn ( ToolGroup $group ): bool => $group->in_chat_by_default() )
			),
			array_values( $this->slugs( $this->settings( [] ) ) )
		);
	}

	/**
	 * The distinction the null default exists for: an admin who unticks
	 * everything means it, and must not be handed the defaults back on the next
	 * page load.
	 */
	public function test_unticking_everything_is_honoured_rather_than_defaulted(): void {
		$this->assertSame( [], $this->slugs( $this->settings( [ 'tool_groups' => [] ] ) ) );
	}

	public function test_a_stored_selection_is_returned_as_groups(): void {
		$this->assertSame( [ 'coupons' ], $this->slugs( $this->settings( [ 'tool_groups' => [ 'coupons' ] ] ) ) );
	}

	/**
	 * A group dropped in a later release must fall out of a stored selection
	 * rather than fatal on every chat render.
	 */
	public function test_a_slug_that_is_no_longer_a_group_is_dropped(): void {
		$this->assertSame(
			[ 'products' ],
			$this->slugs( $this->settings( [ 'tool_groups' => [ 'products', 'gone_in_a_later_release' ] ] ) )
		);
	}

	public function test_a_corrupt_stored_value_falls_back_to_the_defaults(): void {
		$this->assertNotSame( [], $this->slugs( $this->settings( [ 'tool_groups' => 'products' ] ) ) );
	}

	public function test_saving_stores_slugs_and_discards_unknown_ones(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$saved = null;
		Functions\when( 'update_option' )->alias(
			static function ( string $name, mixed $value ) use ( &$saved ): bool {
				$saved = $value;

				return true;
			}
		);

		( new ChatSettings() )->save_groups( [ 'orders', 'not_a_group' ] );

		$this->assertSame( [ 'orders' ], $saved['tool_groups'] );
	}

	/** Saving areas must not disturb the model connection stored alongside them. */
	public function test_saving_areas_leaves_the_connected_model_alone(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'provider' => 'anthropic',
				'model'    => 'claude-opus-5',
				'api_key'  => 'sk-secret',
			]
		);

		$saved = null;
		Functions\when( 'update_option' )->alias(
			static function ( string $name, mixed $value ) use ( &$saved ): bool {
				$saved = $value;

				return true;
			}
		);

		( new ChatSettings() )->save_groups( [ 'orders' ] );

		$this->assertSame( 'claude-opus-5', $saved['model'] );
		$this->assertSame( 'sk-secret', $saved['api_key'] );
	}
}
