<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Counterhand\Features\WooCommerceTools\Domain\AcfFieldNamePolicy;
use Counterhand\Tests\Unit\TestCase;

final class AcfFieldNamePolicyTest extends TestCase {

	public function test_a_protected_name_is_refused_because_it_is_the_meta_key(): void {
		$this->assertFalse( ( new AcfFieldNamePolicy() )->verdict( [ 'acf' => [ '_price' => '0.01' ] ] )->allowed );
	}

	public function test_a_plain_field_is_allowed(): void {
		$this->assertTrue( ( new AcfFieldNamePolicy() )->verdict( [ 'acf' => [ 'care_notes' => 'x' ] ] )->allowed );
	}

	public function test_nothing_sent_is_allowed(): void {
		$this->assertTrue( ( new AcfFieldNamePolicy() )->verdict( [ 'id' => 1 ] )->allowed );
	}
}
