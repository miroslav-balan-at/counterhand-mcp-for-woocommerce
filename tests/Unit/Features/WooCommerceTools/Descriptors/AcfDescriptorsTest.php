<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\WooCommerceTools\Descriptors\AcfDescriptors;
use Counterhand\Tests\Unit\TestCase;

final class AcfDescriptorsTest extends TestCase {

	/** Naming anything else, or leaving the input open, would hand this grant what content:write gates. */
	public function test_every_operation_takes_only_the_id_and_stays_closed(): void {
		foreach ( ( new AcfDescriptors() )->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				$this->assertSame( [ 'id' ], $operation->fields->input, $operation->name->value );
				$this->assertFalse( $operation->accepts_additional(), $operation->name->value );
				$this->assertContains( array_keys( $operation->declared_arguments ), [ [], [ 'acf' ] ], $operation->name->value );
			}
		}
	}

	public function test_it_offers_nothing_for_the_custom_field_tables_directly(): void {
		foreach ( ( new AcfDescriptors() )->resources() as $resource ) {
			$this->assertNull( $resource->meta );
		}
	}
}
