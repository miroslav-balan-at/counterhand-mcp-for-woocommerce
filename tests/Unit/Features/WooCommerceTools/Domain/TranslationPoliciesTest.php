<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Brain\Monkey\Filters;
use Counterhand\Features\WooCommerceTools\Domain\ExistingTranslationPolicy;
use Counterhand\Features\WooCommerceTools\Domain\NewTranslationPolicy;
use Counterhand\Features\WooCommerceTools\Domain\TranslatedElements;
use Counterhand\Tests\Unit\TestCase;

final class TranslationPoliciesTest extends TestCase {

	private function records( ?object $details, array $translations = [] ): void {
		Filters\expectApplied( 'wpml_element_language_details' )->andReturn( $details );
		Filters\expectApplied( 'wpml_get_element_translations' )->andReturn( $translations );
		Filters\expectApplied( 'wpml_language_is_active' )->andReturnUsing( static fn ( bool $active, string $language ): bool => in_array( $language, [ 'de', 'en' ], true ) );
		Filters\expectApplied( 'wpml_default_language' )->andReturn( 'de' );
	}

	public function test_a_new_translation_is_allowed_into_a_free_active_language(): void {
		$this->records(
			(object) [
				'trid'                 => 1,
				'language_code'        => 'de',
				'source_language_code' => null,
			],
			[ 'de' => (object) [] ]
		);

		$this->assertTrue(
			( new NewTranslationPolicy( TranslatedElements::posts( 'product' ) ) )->verdict(
				[
					'lang'           => 'en',
					'translation_of' => 5,
				]
			)->allowed
		);
	}

	/** A second translation would orphan the first and answer success. */
	public function test_a_language_that_already_has_a_translation_is_refused(): void {
		$this->records(
			(object) [
				'trid'                 => 1,
				'language_code'        => 'de',
				'source_language_code' => null,
			],
			[ 'en' => (object) [] ]
		);

		$this->assertFalse(
			( new NewTranslationPolicy( TranslatedElements::posts( 'product' ) ) )->verdict(
				[
					'lang'           => 'en',
					'translation_of' => 5,
				]
			)->allowed
		);
	}

	public function test_an_inactive_language_an_unrecorded_original_or_a_missing_argument_is_refused(): void {
		$this->records( null );
		$policy = new NewTranslationPolicy( TranslatedElements::posts( 'product' ) );

		$this->assertFalse(
			$policy->verdict(
				[
					'lang'           => 'fr',
					'translation_of' => 5,
				]
			)->allowed
		);
		$this->assertFalse(
			$policy->verdict(
				[
					'lang'           => 'en',
					'translation_of' => 5,
				]
			)->allowed
		);
		$this->assertFalse( $policy->verdict( [ 'lang' => 'en' ] )->allowed );
		$this->assertFalse( $policy->verdict( [ 'translation_of' => 5 ] )->allowed );
	}

	public function test_only_a_translated_product_can_be_changed(): void {
		$policy = new ExistingTranslationPolicy( TranslatedElements::posts( 'product' ) );

		$this->records(
			(object) [
				'trid'                 => 1,
				'language_code'        => 'en',
				'source_language_code' => 'de',
			]
		);
		$this->assertTrue( $policy->verdict( [ 'id' => 6 ] )->allowed );
	}

	/** An original in a language other than the default is still an original. */
	public function test_an_original_product_is_refused_whatever_its_language(): void {
		$this->records(
			(object) [
				'trid'                 => 1,
				'language_code'        => 'en',
				'source_language_code' => null,
			]
		);

		$this->assertFalse( ( new ExistingTranslationPolicy( TranslatedElements::posts( 'product' ) ) )->verdict( [ 'id' => 5 ] )->allowed );
	}

	public function test_a_product_with_no_recorded_language_or_no_id_is_refused(): void {
		$this->records( null );
		$policy = new ExistingTranslationPolicy( TranslatedElements::posts( 'product' ) );

		$this->assertFalse( $policy->verdict( [ 'id' => 5 ] )->allowed );
		$this->assertFalse( $policy->verdict( [] )->allowed );
	}

	/** It would put an unreleased product on sale in that language. */
	public function test_publishing_needs_a_published_original(): void {
		Filters\expectApplied( 'wpml_element_language_details' )->andReturn(
			(object) [
				'trid'                 => 1,
				'language_code'        => 'de',
				'source_language_code' => 'en',
			]
		);
		Filters\expectApplied( 'wpml_get_element_translations' )->andReturn( [ 'en' => (object) [ 'element_id' => 5 ] ] );
		$status = 'draft';
		\Brain\Monkey\Functions\when( 'get_post_status' )->alias(
			static function () use ( &$status ): string {
				return $status;
			}
		);
		$policy = new ExistingTranslationPolicy( TranslatedElements::posts( 'product' ), original_must_be_published: true );

		$this->assertFalse( $policy->verdict( [ 'id' => 6 ] )->allowed );

		$status = 'publish';
		$this->assertTrue( $policy->verdict( [ 'id' => 6 ] )->allowed );
	}
}
