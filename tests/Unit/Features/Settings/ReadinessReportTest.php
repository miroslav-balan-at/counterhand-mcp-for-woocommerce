<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Features\Settings;

use AgentGateMcp\Features\Settings\ReadinessReport;
use AgentGateMcp\Features\Settings\ReadinessStatus;
use AgentGateMcp\Tests\Unit\TestCase;

final class ReadinessReportTest extends TestCase {

	public function test_serializes_to_the_shape_connect_js_switches_on(): void {
		$report = new ReadinessReport( ReadinessStatus::Local, 'Local site', 'The .local suffix…' );

		self::assertSame(
			[
				'status'  => 'local',
				'message' => 'Local site',
				'detail'  => 'The .local suffix…',
			],
			$report->to_array()
		);
	}

	public function test_status_values_match_the_chip_classes(): void {
		self::assertSame( [ 'ok', 'local', 'error' ], array_column( ReadinessStatus::cases(), 'value' ) );
	}
}
