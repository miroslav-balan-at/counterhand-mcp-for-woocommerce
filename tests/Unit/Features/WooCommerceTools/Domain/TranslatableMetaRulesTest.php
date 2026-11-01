<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Brain\Monkey\Filters;
use Counterhand\Features\WooCommerceTools\Domain\MetaKeyPolicy;
use Counterhand\Features\WooCommerceTools\Domain\TranslatableMetaRules;
use Counterhand\Tests\Unit\TestCase;

final class TranslatableMetaRulesTest extends TestCase {

	public function test_only_translated_text_is_reachable(): void {
		Filters\expectApplied( 'wpml_setting' )->andReturn(
			[
				'custom_fields_translation' => [
					'_purchase_note'      => 2,
					'_mini_desc'          => '2',
					'_price'              => 1,
					'_ignored'            => 0,
					'_downloadable_files' => 2,
					'_product_url'        => 2,
					'_yoast_wpseo_title'  => 2,
					'rank_math_title'     => 2,
					'direct_debit_iban'   => 2,
					'wp_capabilities'     => 2,
				],
			]
		);

		$this->assertSame( [ '_purchase_note', '_mini_desc' ], ( new TranslatableMetaRules( new MetaKeyPolicy( 'wp_' ) ) )->keys() );
	}

	public function test_a_store_without_the_setting_offers_nothing(): void {
		Filters\expectApplied( 'wpml_setting' )->andReturn( null );

		$this->assertSame( [], ( new TranslatableMetaRules( new MetaKeyPolicy( 'wp_' ) ) )->keys() );
	}
}
