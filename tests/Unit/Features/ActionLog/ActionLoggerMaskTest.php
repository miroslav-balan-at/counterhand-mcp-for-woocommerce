<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\ActionLog;

use Counterhand\Features\ActionLog\ActionLogger;
use Counterhand\Tests\Unit\TestCase;

final class ActionLoggerMaskTest extends TestCase {

	public function test_a_report_date_range_is_kept(): void {
		self::assertSame(
			'{"date_min":"2026-11-02T00:00:00","date_max":"2026-11-08 23:59:59"}',
			( new ActionLogger() )->mask_pii( '{"date_min":"2026-11-02T00:00:00","date_max":"2026-11-08 23:59:59"}' )
		);
	}

	public function test_phone_numbers_are_still_masked_even_when_shaped_like_a_date(): void {
		$logger = new ActionLogger();

		self::assertSame( '{"phone":"***67"}', $logger->mask_pii( '{"phone":"+43 664 1234567"}' ) );
		self::assertSame( '{"phone":"***34"}', $logger->mask_pii( '{"phone":"0664-12-34"}' ) );
		self::assertSame( '{"phone":"***31"}', $logger->mask_pii( '{"phone":"0800-02-31"}' ) );
	}
}
