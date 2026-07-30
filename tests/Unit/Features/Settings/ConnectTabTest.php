<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Settings;

use Counterhand\Features\Settings\ConnectTab;
use Counterhand\Tests\Unit\TestCase;

final class ConnectTabTest extends TestCase {

	protected function tearDown(): void {
		unset( $_GET['view'] );
		parent::tearDown();
	}

	public function test_defaults_to_adding_an_app(): void {
		self::assertSame( ConnectTab::Apps, ConnectTab::current() );
	}

	public function test_reads_the_view_parameter(): void {
		$_GET['view'] = 'connections';

		self::assertSame( ConnectTab::Connections, ConnectTab::current() );
	}

	public function test_an_unknown_view_falls_back_rather_than_erroring(): void {
		$_GET['view'] = 'nonsense';

		self::assertSame( ConnectTab::Apps, ConnectTab::current() );
	}

	public function test_every_tab_is_labelled(): void {
		foreach ( ConnectTab::cases() as $tab ) {
			self::assertNotSame( '', $tab->label() );
		}
	}
}
