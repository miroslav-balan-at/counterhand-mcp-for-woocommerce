<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit;

use Brain\Monkey;

abstract class TestCase extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
