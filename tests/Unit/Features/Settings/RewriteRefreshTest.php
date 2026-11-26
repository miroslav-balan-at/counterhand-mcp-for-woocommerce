<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Settings;

use Counterhand\Features\Settings\RewriteRefresh;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * Switching AI access on must bring /mcp back even when a flush dropped its rules
 * while access was off, and saving anything else must not flush at all.
 */
final class RewriteRefreshTest extends TestCase {

	/** @var array<string, mixed> */
	private array $options = [];

	private int $flushes = 0;

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'update_option' )->alias(
			function ( string $name, mixed $value ): bool {
				$this->options[ $name ] = $value;

				return true;
			}
		);
		Functions\when( 'get_option' )->alias( fn ( string $name ): mixed => $this->options[ $name ] ?? false );
		Functions\when( 'delete_option' )->alias(
			function ( string $name ): bool {
				unset( $this->options[ $name ] );

				return true;
			}
		);
		Functions\when( 'flush_rewrite_rules' )->alias(
			function (): void {
				++$this->flushes;
			}
		);
	}

	public function test_switching_access_on_flushes_once_on_the_next_request(): void {
		$refresh = new RewriteRefresh();

		$refresh->queue_when_switched( [ 'enabled' => false ], [ 'enabled' => true ] );
		self::assertSame( 0, $this->flushes, 'not in the request that saved it, which still has the old rules' );

		$refresh->flush_queued();
		$refresh->flush_queued();
		self::assertSame( 1, $this->flushes );
		self::assertArrayNotHasKey( RewriteRefresh::QUEUE_OPTION, $this->options );
	}

	public function test_switching_access_off_flushes_too(): void {
		$refresh = new RewriteRefresh();

		$refresh->queue_when_switched( [ 'enabled' => true ], [ 'enabled' => false ] );
		$refresh->flush_queued();

		self::assertSame( 1, $this->flushes );
	}

	public function test_saving_other_settings_does_not_flush(): void {
		$refresh = new RewriteRefresh();

		$refresh->queue_when_switched( [ 'enabled' => true, 'rate_limit_per_minute' => 60 ], [ 'enabled' => true, 'rate_limit_per_minute' => 30 ] );
		$refresh->flush_queued();

		self::assertSame( 0, $this->flushes );
	}

	public function test_the_first_save_with_access_on_flushes(): void {
		$refresh = new RewriteRefresh();

		$refresh->queue_when_added( 'counterhand_settings', [ 'enabled' => true ] );
		$refresh->flush_queued();

		self::assertSame( 1, $this->flushes );
	}

	public function test_the_first_save_with_access_off_does_not_flush(): void {
		$refresh = new RewriteRefresh();

		$refresh->queue_when_added( 'counterhand_settings', [ 'enabled' => false ] );
		$refresh->flush_queued();

		self::assertSame( 0, $this->flushes );
	}
}
