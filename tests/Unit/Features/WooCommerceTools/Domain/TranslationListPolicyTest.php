<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Brain\Monkey\Filters;
use Counterhand\Features\WooCommerceTools\Domain\TranslationListPolicy;
use Counterhand\Tests\Unit\TestCase;

final class TranslationListPolicyTest extends TestCase {

	public function test_only_an_active_language_or_none_is_allowed(): void {
		Filters\expectApplied( 'wpml_language_is_active' )->andReturnUsing( static fn ( bool $active, string $language ): bool => 'de' === $language );
		$policy = new TranslationListPolicy();

		$this->assertTrue( $policy->verdict( [] )->allowed );
		$this->assertTrue( $policy->verdict( [ 'lang' => 'de' ] )->allowed );
		$this->assertFalse( $policy->verdict( [ 'lang' => 'all' ] )->allowed, 'it would lift the language filters for the rest of the request' );
		$this->assertFalse( $policy->verdict( [ 'lang' => 'fr' ] )->allowed );
	}
}
