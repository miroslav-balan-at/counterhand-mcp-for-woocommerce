<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\ActionLog;

use Counterhand\Features\ActionLog\ActionLogger;
use Counterhand\Tests\Unit\TestCase;

final class ActionLoggerPersonalFieldsTest extends TestCase {

	/** Names and addresses have no pattern, so only their field gives them away. */
	public function test_names_and_addresses_are_masked_by_field_at_any_depth(): void {
		$method = new \ReflectionMethod( ActionLogger::class, 'without_personal_fields' );

		$masked = $method->invoke(
			null,
			[
				'id'      => 5,
				'billing' => [
					'first_name' => 'Ada',
					'address_1'  => 'Hauptstr. 1',
					'country'    => 'AT',
				],
				'note'    => 'Call Ada',
			]
		);

		$this->assertSame( 5, $masked['id'] );
		$this->assertSame( '***', $masked['billing']['first_name'] );
		$this->assertSame( '***', $masked['billing']['address_1'] );
		$this->assertSame( 'AT', $masked['billing']['country'] );
		$this->assertSame( '***', $masked['note'] );
	}
}
