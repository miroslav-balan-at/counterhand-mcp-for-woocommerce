<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Counterhand\Features\WooCommerceTools\Domain\StringTranslationRules;
use Counterhand\Tests\Unit\TestCase;

final class StringTranslationRulesTest extends TestCase {

	/** @dataProvider translations */
	public function test_a_translation_keeps_what_the_code_needs( string $original, string $translation, bool $allowed ): void {
		$this->assertSame( $allowed, null === ( new StringTranslationRules() )->refusal( $original, $translation ) );
	}

	/** @return array<string, array{string, string, bool}> */
	public static function translations(): array {
		return [
			'plain text'                    => [ 'Add to cart', 'In den Warenkorb', true ],
			'placeholders kept'             => [ 'Order {order_number} for %s', 'Bestellung {order_number} für %s', true ],
			'reordered'                     => [ '%1$s of %2$s', '%2$s: %1$s', true ],
			'a literal percent kept'        => [ '50% off', '50% Rabatt', true ],
			'an escaped percent'            => [ '%d%% off', '%d%% Rabatt', true ],
			'empty'                         => [ 'Subject', '', false ],
			'only spaces'                   => [ 'Subject', '   ', false ],
			'a named placeholder lost'      => [ 'Order {order_number}', 'Bestellung', false ],
			'a named placeholder renamed'   => [ 'Order {order_number}', 'Bestellung {nummer}', false ],
			'a printf placeholder lost'     => [ 'For %s', 'Für', false ],
			'one too many, which throws'    => [ 'For %s', 'Für %s %s', false ],
			'a stray percent, which throws' => [ 'For %s', '50% Rabatt für %s', false ],
			'a percent reading as a flag'   => [ 'For %s', '50% sicher %s', false ],
		];
	}
}
